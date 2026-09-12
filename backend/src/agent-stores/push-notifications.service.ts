import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import * as webPush from 'web-push';
import { createHash } from 'crypto';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class PushNotificationsService {
  private readonly logger = new Logger(PushNotificationsService.name);
  private readonly publicKey?: string;
  private readonly enabled: boolean;

  constructor(private prisma: PrismaService, config: ConfigService) {
    this.publicKey = config.get<string>('VAPID_PUBLIC_KEY');
    const privateKey = config.get<string>('VAPID_PRIVATE_KEY');
    const subject = config.get<string>('VAPID_SUBJECT') || 'mailto:admin@freedomdata.app';
    this.enabled = Boolean(this.publicKey && privateKey);
    if (this.publicKey && privateKey) webPush.setVapidDetails(subject, this.publicKey, privateKey);
    else this.logger.warn('Push notifications are disabled because VAPID keys are missing');
  }

  configuration() {
    return { enabled: this.enabled, publicKey: this.enabled ? this.publicKey : null };
  }

  async subscribe(userId: string, endpoint: string, keys: { p256dh: string; auth: string }) {
    const endpointHash = createHash('sha256').update(endpoint).digest('hex');
    return this.prisma.pushSubscription.upsert({
      where: { endpointHash },
      create: { userId, endpoint, endpointHash, p256dh: keys.p256dh, auth: keys.auth },
      update: { userId, endpoint, p256dh: keys.p256dh, auth: keys.auth },
      select: { id: true, createdAt: true },
    });
  }

  async unsubscribe(userId: string, endpoint: string) {
    const endpointHash = createHash('sha256').update(endpoint).digest('hex');
    await this.prisma.pushSubscription.deleteMany({ where: { userId, endpointHash } });
    return { removed: true };
  }

  async notifyNewPayment(agentId: string, request: { id: string; recipient: string; transactionId: string | null; product: { network: string; bundleGb: unknown } }) {
    if (!this.enabled) return;
    const subscriptions = await this.prisma.pushSubscription.findMany({ where: { userId: agentId } });
    const payload = JSON.stringify({
      title: 'New payment awaiting approval',
      body: `${request.product.network} ${Number(request.product.bundleGb)}GB for ${request.recipient}${request.transactionId ? ` • ID: ${request.transactionId}` : ' • Screenshot attached'}`,
      url: `/agent?request=${request.id}`,
      tag: `agent-order-${request.id}`,
    });

    await Promise.allSettled(subscriptions.map(async (subscription) => {
      try {
        await webPush.sendNotification({
          endpoint: subscription.endpoint,
          keys: { p256dh: subscription.p256dh, auth: subscription.auth },
        }, payload);
      } catch (error: any) {
        if (error?.statusCode === 404 || error?.statusCode === 410) {
          await this.prisma.pushSubscription.delete({ where: { id: subscription.id } });
        } else {
          this.logger.warn(`Push delivery failed for subscription ${subscription.id}: ${error?.statusCode || error?.message || 'unknown error'}`);
        }
      }
    }));
  }
}
