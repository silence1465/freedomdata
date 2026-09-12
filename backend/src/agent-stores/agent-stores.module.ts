import { Module } from '@nestjs/common';
import { AuthModule } from '../auth/auth.module';
import { OrdersModule } from '../orders/orders.module';
import { AgentStoresController } from './agent-stores.controller';
import { AgentStoresService } from './agent-stores.service';
import { PushNotificationsService } from './push-notifications.service';

@Module({
  imports: [AuthModule, OrdersModule],
  controllers: [AgentStoresController],
  providers: [AgentStoresService, PushNotificationsService],
})
export class AgentStoresModule {}
