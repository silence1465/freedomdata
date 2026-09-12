import { Injectable, BadRequestException, NotFoundException } from '@nestjs/common';
import { InjectQueue } from '@nestjs/bullmq';
import { Queue } from 'bullmq';
import { randomUUID } from 'crypto';
import { PrismaService } from '../prisma/prisma.service';
import { ProductsService } from '../products/products.service';
import { WalletService } from '../wallet/wallet.service';
import { DataSikaClient, isDefinitiveDataSikaRejection } from '../datasika/datasika.client';
import { LedgerType, OrderStatus, ServiceType } from '@prisma/client';

const GHANA_PHONE_REGEX = /^0\d{9}$/;

@Injectable()
export class OrdersService {
  constructor(
    private prisma: PrismaService,
    private products: ProductsService,
    private wallet: WalletService,
    private dataSika: DataSikaClient,
    @InjectQueue('order-status') private statusQueue: Queue,
  ) {}

  /**
   * Places an order:
   * 1. Validates recipient + product availability.
   * 2. Debits the customer's wallet at YOUR sell price (ledger entry created).
   * 3. Calls DataSika with a deterministic Idempotency-Key derived from our own order id,
   *    so a retried request from the frontend never double-charges DataSika.
   * 4. Persists the order as PENDING and enqueues a status-poll job.
   *
   * If the DataSika call itself fails outright (not just returns Pending), the customer's
   * wallet debit is reversed immediately rather than waiting on a refund from DataSika.
   */
  async placeOrder(userId: string, productId: string, recipient: string) {
    if (!GHANA_PHONE_REGEX.test(recipient)) {
      throw new BadRequestException('invalid_recipient');
    }

    const [product, user] = await Promise.all([
      this.products.findById(productId),
      this.prisma.user.findUniqueOrThrow({ where: { id: userId } }),
    ]);
    if (!product.isAvailable) {
      throw new BadRequestException('product_unavailable');
    }

    const orderId = randomUUID();
    const idempotencyKey = `order-${orderId}`;
    const sellPrice = Number(user.role === 'AGENT' && product.agentPrice ? product.agentPrice : product.sellPrice);

    // Create the order row first (status PENDING) so the wallet debit can reference
    // its id directly — avoids any ambiguity about which ledger entry belongs to it.
    const order = await this.prisma.order.create({
      data: {
        id: orderId,
        idempotencyKey,
        userId,
        productId,
        recipient,
        amountCharged: sellPrice,
        costAmount: product.costPrice,
        status: OrderStatus.PENDING,
      },
    });

    // Debit next — if this throws (insufficient_balance), delete the order row and bail.
    try {
      await this.wallet.debit(userId, sellPrice, LedgerType.PURCHASE, { orderId: order.id });
    } catch (err) {
      await this.prisma.order.delete({ where: { id: order.id } });
      throw err;
    }

    try {
      const buyFn =
        product.serviceType === ServiceType.MTN_EXPRESS
          ? this.dataSika.buyExpress.bind(this.dataSika)
          : this.dataSika.buyData.bind(this.dataSika);

      const dsResponse = await buyFn({
        product_id: product.dataSikaId,
        recipient,
        idempotencyKey,
      });

      await this.prisma.order.update({
        where: { id: order.id },
        data: { dataSikaOrderId: dsResponse.order_id, status: OrderStatus.PENDING },
      });
    } catch (err) {
      // The buy call itself failed (not a "pending, poll later" case) — reverse the debit.
      if (isDefinitiveDataSikaRejection(err)) {
        await this.wallet.credit(userId, sellPrice, LedgerType.REFUND, {
          orderId: order.id,
          reference: `dispatch_refund:${order.id}`,
        });
        await this.prisma.order.update({
          where: { id: order.id },
          data: { status: OrderStatus.FAILED, failureReason: 'dispatch_rejected' },
        });
        throw err;
      }
      await this.prisma.order.update({
        where: { id: order.id },
        data: { failureReason: 'dispatch_confirmation_pending' },
      });
    }

    // Poll shortly after; the processor re-enqueues itself with backoff until terminal.
    await this.statusQueue.add(
      'poll',
      { orderId: order.id },
      { delay: 15_000, attempts: 1, removeOnComplete: true, removeOnFail: true },
    );

    return this.prisma.order.findUniqueOrThrow({ where: { id: order.id } });
  }

  async getOrder(orderId: string) {
    const order = await this.prisma.order.findUnique({
      where: { id: orderId },
      include: { product: true },
    });
    if (!order) throw new NotFoundException('order_not_found');
    return order;
  }

  async listForUser(userId: string) {
    return this.prisma.order.findMany({
      where: { userId },
      orderBy: { createdAt: 'desc' },
      include: { product: true },
    });
  }
}
