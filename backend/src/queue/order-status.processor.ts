import { Processor, WorkerHost, InjectQueue } from '@nestjs/bullmq';
import { Job, Queue } from 'bullmq';
import { Logger } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { DataSikaClient, isDefinitiveDataSikaRejection } from '../datasika/datasika.client';
import { WalletService } from '../wallet/wallet.service';
import { RealtimeGateway } from '../realtime/realtime.gateway';
import { LedgerType, OrderStatus, Prisma, ServiceType } from '@prisma/client';

const TERMINAL_STATUSES: OrderStatus[] = [
  OrderStatus.DELIVERED,
  OrderStatus.FAILED,
  OrderStatus.REFUNDED,
];

const MAX_POLL_ATTEMPTS = 20; // ~ generous ceiling; escalate to manual review after this

const STATUS_MAP: Record<string, OrderStatus> = {
  pending: OrderStatus.PENDING,
  processing: OrderStatus.PROCESSING,
  delivered: OrderStatus.DELIVERED,
  failed: OrderStatus.FAILED,
  refunded: OrderStatus.REFUNDED,
  refund_processing: OrderStatus.REFUND_PROCESSING,
};

/**
 * Polls /api-order-status for a single order. Re-enqueues itself with backoff
 * until the order reaches a terminal state (delivered/failed/refunded), respecting
 * DataSika's 120/min status rate limit via this queue's own concurrency + backoff.
 */
@Processor('order-status', { concurrency: 5 })
export class OrderStatusProcessor extends WorkerHost {
  private readonly logger = new Logger(OrderStatusProcessor.name);

  constructor(
    private prisma: PrismaService,
    private dataSika: DataSikaClient,
    private wallet: WalletService,
    private realtime: RealtimeGateway,
    @InjectQueue('order-status') private statusQueue: Queue,
  ) {
    super();
  }

  async process(job: Job<{ orderId: string }>): Promise<void> {
    const { orderId } = job.data;

    const order = await this.prisma.order.findUnique({
      where: { id: orderId },
      include: { product: true },
    });
    if (!order) {
      this.logger.warn(`Order ${orderId} not found, dropping poll job`);
      return;
    }

    if (TERMINAL_STATUSES.includes(order.status)) {
      return; // already resolved, e.g. by a concurrent job
    }

    if (!order.dataSikaOrderId) {
      try {
        const buyFn = order.product.serviceType === ServiceType.MTN_EXPRESS
          ? this.dataSika.buyExpress.bind(this.dataSika)
          : this.dataSika.buyData.bind(this.dataSika);
        const response = await buyFn({
          product_id: order.product.dataSikaId,
          recipient: order.recipient,
          idempotencyKey: order.idempotencyKey,
        });
        await this.prisma.order.update({
          where: { id: orderId },
          data: { dataSikaOrderId: response.order_id, failureReason: null },
        });
      } catch (err) {
        if (isDefinitiveDataSikaRejection(err)) {
          await this.wallet.credit(order.userId, Number(order.amountCharged), LedgerType.REFUND, {
            orderId: order.id,
            reference: `dispatch_refund:${order.id}`,
          });
          const failed = await this.prisma.order.update({
            where: { id: orderId },
            data: { status: OrderStatus.FAILED, failureReason: 'dispatch_rejected' },
          });
          this.realtime.emitOrderUpdate(order.id, order.userId, {
            id: failed.id,
            status: failed.status,
            updatedAt: failed.updatedAt,
          });
          return;
        }
      }
      // Shouldn't normally happen — the buy call always returns an order_id on success —
      // but guard against a race where the poll fires before the id was persisted.
      await this.requeue(orderId, order.pollAttempts, 10_000);
      return;
    }

    let statusResponse;
    try {
      statusResponse = await this.dataSika.getOrderStatus(order.dataSikaOrderId);
    } catch (err: any) {
      // 429: respect retry_after from DataSika
      if (err?.getStatus?.() === 429) {
        const retryAfterMs = (err.getResponse?.()?.retry_after ?? 5) * 1000;
        await this.requeue(orderId, order.pollAttempts, retryAfterMs);
        return;
      }
      // Any other transient error: backoff and retry, up to the attempt ceiling
      await this.requeue(orderId, order.pollAttempts, this.backoffMs(order.pollAttempts));
      return;
    }

    const newStatus = STATUS_MAP[statusResponse.status] ?? OrderStatus.PENDING;

    if (newStatus === order.status) {
      // No change yet — keep polling if not terminal
      if (!TERMINAL_STATUSES.includes(newStatus)) {
        await this.requeue(orderId, order.pollAttempts, this.backoffMs(order.pollAttempts));
      }
      return;
    }

    // If DataSika refunded a failed dispatch, credit it back to the customer's wallet too —
    // otherwise you'd be out the cost while the customer never got charged back.
    if (newStatus === OrderStatus.REFUNDED && order.status !== OrderStatus.REFUNDED) {
      try {
        await this.wallet.credit(order.userId, Number(order.amountCharged), LedgerType.REFUND, {
          orderId: order.id,
          reference: `datasika_auto_refund:${order.id}`,
        });
      } catch (err) {
        const alreadyCredited = err instanceof Prisma.PrismaClientKnownRequestError && err.code === 'P2002';
        if (!alreadyCredited) throw err;
      }
    }

    const updated = await this.prisma.order.update({
      where: { id: orderId },
      data: { status: newStatus, pollAttempts: { increment: 1 } },
    });

    this.realtime.emitOrderUpdate(order.id, order.userId, {
      id: updated.id,
      status: updated.status,
      updatedAt: updated.updatedAt,
    });

    if (!TERMINAL_STATUSES.includes(newStatus)) {
      await this.requeue(orderId, updated.pollAttempts, this.backoffMs(updated.pollAttempts));
    } else {
      this.logger.log(`Order ${orderId} reached terminal status: ${newStatus}`);
    }
  }

  private async requeue(orderId: string, attempts: number, delayMs: number) {
    if (attempts >= MAX_POLL_ATTEMPTS) {
      this.logger.warn(`Order ${orderId} exceeded max poll attempts — needs manual review`);
      // Left as PENDING/PROCESSING intentionally — surface these in the admin dashboard
      // as "stuck orders" rather than guessing a terminal state.
      return;
    }
    await this.prisma.order.update({
      where: { id: orderId },
      data: { pollAttempts: { increment: 1 } },
    });
    await this.statusQueue.add(
      'poll',
      { orderId },
      { delay: delayMs, attempts: 1, removeOnComplete: true, removeOnFail: true },
    );
  }

  /** Simple exponential backoff, capped at 60s, starting at 10s. */
  private backoffMs(attempts: number): number {
    return Math.min(10_000 * 2 ** Math.max(attempts - 1, 0), 60_000);
  }
}
