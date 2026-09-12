import { Module } from '@nestjs/common';
import { BullModule } from '@nestjs/bullmq';
import { OrdersService } from './orders.service';
import { OrdersController } from './orders.controller';
import { ProductsModule } from '../products/products.module';
import { WalletModule } from '../wallet/wallet.module';
import { DataSikaModule } from '../datasika/datasika.module';

@Module({
  imports: [
    BullModule.registerQueue({ name: 'order-status' }),
    ProductsModule,
    WalletModule,
    DataSikaModule,
  ],
  providers: [OrdersService],
  controllers: [OrdersController],
  exports: [OrdersService],
})
export class OrdersModule {}
