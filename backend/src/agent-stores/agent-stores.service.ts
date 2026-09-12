import { BadRequestException, ConflictException, ForbiddenException, Injectable, NotFoundException } from '@nestjs/common';
import { AgentStoreOrderStatus, Prisma, Role } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';
import { OrdersService } from '../orders/orders.service';
import { AgentStoreReviewAction, ReviewAgentStoreOrderDto, SubmitAgentStoreOrderDto } from './agent-stores.dto';

const MAX_PROOF_SIZE = 5 * 1024 * 1024;
const PROOF_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);

@Injectable()
export class AgentStoresService {
  constructor(private prisma: PrismaService, private orders: OrdersService) {}

  private agentCode(userId: string) {
    return `freedom-${userId.replace(/-/g, '').slice(0, 16)}`;
  }

  async dashboard(agentId: string) {
    const agent = await this.prisma.user.findUniqueOrThrow({ where: { id: agentId } });
    if (agent.role !== Role.AGENT) throw new ForbiddenException('agent_account_required');

    const agentCode = agent.agentCode ?? this.agentCode(agent.id);
    if (!agent.agentCode) {
      await this.prisma.user.update({ where: { id: agent.id }, data: { agentCode } });
    }

    const requests = await this.prisma.agentStoreOrder.findMany({
      where: { agentId },
      include: { product: true, order: true },
      orderBy: { createdAt: 'desc' },
      take: 100,
    });

    return {
      agentCode,
      requests: requests.map(({ proofData, ...request }) => ({ ...request, hasProof: Boolean(proofData) })),
    };
  }

  async publicStore(code: string) {
    const agent = await this.prisma.user.findFirst({
      where: { agentCode: code, role: Role.AGENT },
      select: { id: true, name: true, agentCode: true },
    });
    if (!agent) throw new NotFoundException('agent_store_not_found');

    const products = await this.prisma.product.findMany({
      where: { isAvailable: true },
      orderBy: [{ network: 'asc' }, { bundleGb: 'asc' }],
    });
    return {
      agent: { name: agent.name ?? 'Freedom Data Agent', code: agent.agentCode },
      products: products.map((product) => ({
        id: product.id,
        network: product.network,
        bundleGb: product.bundleGb,
        serviceType: product.serviceType,
        price: product.sellPrice,
      })),
    };
  }

  async submit(code: string, dto: SubmitAgentStoreOrderDto, file?: { buffer: Buffer; mimetype: string; size: number }) {
    const agent = await this.prisma.user.findFirst({ where: { agentCode: code, role: Role.AGENT } });
    if (!agent) throw new NotFoundException('agent_store_not_found');
    if (!dto.transactionId?.trim() && !file) {
      throw new BadRequestException('transaction_id_or_proof_required');
    }
    if (file && (file.size > MAX_PROOF_SIZE || !PROOF_TYPES.has(file.mimetype))) {
      throw new BadRequestException('invalid_payment_proof');
    }

    const product = await this.prisma.product.findUnique({ where: { id: dto.productId } });
    if (!product?.isAvailable) throw new BadRequestException('product_unavailable');

    try {
      const request = await this.prisma.agentStoreOrder.create({
        data: {
          agentId: agent.id,
          productId: product.id,
          recipient: dto.recipient,
          customerName: dto.customerName?.trim() || null,
          customerPhone: dto.customerPhone || null,
          transactionId: dto.transactionId?.trim() || null,
          proofData: file ? Uint8Array.from(file.buffer) : undefined,
          proofMime: file?.mimetype,
        },
        include: { product: true },
      });
      const { proofData, ...safeRequest } = request;
      return { ...safeRequest, hasProof: Boolean(proofData) };
    } catch (error) {
      if (error instanceof Prisma.PrismaClientKnownRequestError && error.code === 'P2002') {
        throw new ConflictException('transaction_id_already_submitted');
      }
      throw error;
    }
  }

  async review(agentId: string, requestId: string, dto: ReviewAgentStoreOrderDto) {
    const request = await this.prisma.agentStoreOrder.findFirst({ where: { id: requestId, agentId } });
    if (!request) throw new NotFoundException('store_order_not_found');
    if (request.status === AgentStoreOrderStatus.APPROVED || request.status === AgentStoreOrderStatus.REJECTED) {
      throw new ConflictException('store_order_already_finalized');
    }

    if (dto.action === AgentStoreReviewAction.HOLD || dto.action === AgentStoreReviewAction.REJECT) {
      return this.prisma.agentStoreOrder.update({
        where: { id: request.id },
        data: {
          status: dto.action === AgentStoreReviewAction.HOLD ? AgentStoreOrderStatus.HOLD : AgentStoreOrderStatus.REJECTED,
          reviewNote: dto.note?.trim() || null,
          reviewedAt: new Date(),
        },
        include: { product: true, order: true },
      });
    }

    const claimed = await this.prisma.agentStoreOrder.updateMany({
      where: {
        id: request.id,
        agentId,
        status: { in: [AgentStoreOrderStatus.SUBMITTED, AgentStoreOrderStatus.HOLD] },
        OR: [{ reviewNote: null }, { reviewNote: { not: 'approval_in_progress' } }],
      },
      data: { status: AgentStoreOrderStatus.HOLD, reviewNote: 'approval_in_progress' },
    });
    if (claimed.count !== 1) throw new ConflictException('store_order_is_being_processed');

    try {
      const order = await this.orders.placeOrder(
        agentId,
        request.productId,
        request.recipient,
        `agent-store-${request.id}`,
      );
      return await this.prisma.agentStoreOrder.update({
        where: { id: request.id },
        data: {
          orderId: order.id,
          status: AgentStoreOrderStatus.APPROVED,
          reviewNote: dto.note?.trim() || null,
          reviewedAt: new Date(),
        },
        include: { product: true, order: true },
      });
    } catch (error) {
      await this.prisma.agentStoreOrder.update({
        where: { id: request.id },
        data: { status: AgentStoreOrderStatus.HOLD, reviewNote: 'approval_failed_retry' },
      });
      throw error;
    }
  }

  async proof(agentId: string, requestId: string) {
    const request = await this.prisma.agentStoreOrder.findFirst({
      where: { id: requestId, agentId },
      select: { proofData: true, proofMime: true },
    });
    if (!request?.proofData || !request.proofMime) throw new NotFoundException('payment_proof_not_found');
    return { data: Buffer.from(request.proofData), mime: request.proofMime };
  }
}
