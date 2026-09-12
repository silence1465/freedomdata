import { Module, OnModuleInit } from '@nestjs/common';
import { BullModule, InjectQueue } from '@nestjs/bullmq';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { Queue } from 'bullmq';
import { OrderStatusProcessor } from './order-status.processor';
import { CatalogSyncProcessor } from './catalog-sync.processor';
import { WalletModule } from '../wallet/wallet.module';
import { RealtimeModule } from '../realtime/realtime.module';
import { DataSikaModule } from '../datasika/datasika.module';
import { ProductsModule } from '../products/products.module';

@Module({
  imports: [
    BullModule.forRootAsync({
      imports: [ConfigModule],
      inject: [ConfigService],
      useFactory: (config: ConfigService) => ({
        connection: {
          host: config.get<string>('REDIS_HOST'),
          port: config.get<number>('REDIS_PORT'),
          password: config.get<string>('REDIS_PASSWORD') || undefined,
        },
      }),
    }),
    BullModule.registerQueue({ name: 'order-status' }, { name: 'catalog-sync' }),
    WalletModule,
    RealtimeModule,
    DataSikaModule,
    ProductsModule,
  ],
  providers: [OrderStatusProcessor, CatalogSyncProcessor],
})
export class QueueModule implements OnModuleInit {
  constructor(@InjectQueue('catalog-sync') private catalogQueue: Queue) {}

  async onModuleInit() {
    await this.catalogQueue.add('sync', {}, { removeOnComplete: true, removeOnFail: 100 });

    // Repeatable job: refresh pricing/availability from DataSika every 10 minutes.
    await this.catalogQueue.add(
      'sync',
      {},
      { repeat: { every: 10 * 60 * 1000 }, jobId: 'catalog-sync-repeat' },
    );
  }
}
