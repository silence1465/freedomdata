import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { Role } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class AdminAgentsService {
  constructor(private prisma: PrismaService) {}

  list() {
    return this.prisma.user.findMany({
      where: { role: Role.AGENT },
      select: {
        id: true, name: true, phone: true, email: true, agentCode: true,
        walletBalance: true, agentSubscriptionExpiresAt: true, createdAt: true,
        _count: { select: { agentStoreOrders: true } },
      },
      orderBy: { createdAt: 'desc' },
    });
  }

  async extend(agentId: string, days: number) {
    if (!Number.isInteger(days) || days < 1 || days > 3650) throw new BadRequestException('invalid_extension_days');
    const agent = await this.prisma.user.findUnique({ where: { id: agentId } });
    if (!agent || agent.role !== Role.AGENT) throw new NotFoundException('agent_not_found');
    const now = new Date();
    const current = agent.agentSubscriptionExpiresAt;
    const base = current && current > now ? current : now;
    const expiresAt = new Date(base.getTime() + days * 24 * 60 * 60 * 1000);
    return this.prisma.user.update({
      where: { id: agentId }, data: { agentSubscriptionExpiresAt: expiresAt },
      select: { id: true, agentSubscriptionExpiresAt: true },
    });
  }
}
