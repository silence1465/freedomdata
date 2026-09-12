import {
  WebSocketGateway,
  WebSocketServer,
  SubscribeMessage,
  MessageBody,
  ConnectedSocket,
  OnGatewayConnection,
  WsException,
} from '@nestjs/websockets';
import { Server, Socket } from 'socket.io';
import { Logger } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';

/**
 * Rooms:
 *  - `order:<orderId>`   -> anonymous customers watching a single order's tracking page
 *  - `user:<userId>`     -> authenticated users watching all their orders / wallet balance
 *
 * The queue processor (order-status.processor.ts) emits into these rooms whenever
 * a poll detects a status change, so the frontend never has to poll itself.
 */
@WebSocketGateway({
  cors: {
    origin: (origin, callback) => {
      const allowed = process.env.FRONTEND_URL;
      const accepted = !origin || origin === allowed || (process.env.NODE_ENV !== 'production' && !allowed);
      callback(accepted ? null : new Error('origin_not_allowed'), accepted);
    },
  },
})
export class RealtimeGateway implements OnGatewayConnection {
  @WebSocketServer()
  server: Server;

  private readonly logger = new Logger(RealtimeGateway.name);

  constructor(private readonly jwt: JwtService) {}

  async handleConnection(client: Socket) {
    const authToken = client.handshake.auth?.token;
    const header = client.handshake.headers.authorization;
    const token = authToken || (header?.startsWith('Bearer ') ? header.slice(7) : undefined);
    if (token) {
      try {
        const payload = await this.jwt.verifyAsync<{ sub: string }>(token);
        client.data.userId = payload.sub;
      } catch {
        client.disconnect(true);
        return;
      }
    }
    this.logger.debug(`Client connected: ${client.id}`);
  }

  @SubscribeMessage('subscribe:order')
  onSubscribeOrder(@MessageBody() orderId: string, @ConnectedSocket() client: Socket) {
    client.join(`order:${orderId}`);
    return { subscribed: `order:${orderId}` };
  }

  @SubscribeMessage('subscribe:user')
  onSubscribeUser(@ConnectedSocket() client: Socket) {
    const userId = client.data.userId as string | undefined;
    if (!userId) throw new WsException('authentication_required');
    client.join(`user:${userId}`);
    return { subscribed: `user:${userId}` };
  }

  emitOrderUpdate(orderId: string, userId: string, payload: unknown) {
    this.server.to(`order:${orderId}`).emit('order:update', payload);
    this.server.to(`user:${userId}`).emit('order:update', payload);
  }

  emitWalletUpdate(userId: string, payload: unknown) {
    this.server.to(`user:${userId}`).emit('wallet:update', payload);
  }
}
