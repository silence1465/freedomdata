import { Processor, WorkerHost } from '@nestjs/bullmq';
import { Job } from 'bullmq';
import { Logger } from '@nestjs/common';
import { ProductsService } from '../products/products.service';

@Processor('catalog-sync', { concurrency: 1 })
export class CatalogSyncProcessor extends WorkerHost {
  private readonly logger = new Logger(CatalogSyncProcessor.name);

  constructor(private products: ProductsService) {
    super();
  }

  async process(_job: Job): Promise<void> {
    try {
      const result = await this.products.syncCatalog();
      this.logger.log(`Scheduled catalog sync: ${JSON.stringify(result)}`);
    } catch (err) {
      this.logger.error(`Scheduled catalog sync failed: ${err}`);
    }
  }
}
