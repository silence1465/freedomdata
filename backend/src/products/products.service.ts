import { Injectable, Logger } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { DataSikaClient } from '../datasika/datasika.client';
import { ServiceType } from '@prisma/client';

const DEFAULT_MARKUP_PERCENT = 15; // fallback margin when a product is first synced

@Injectable()
export class ProductsService {
  private readonly logger = new Logger(ProductsService.name);

  constructor(
    private prisma: PrismaService,
    private dataSika: DataSikaClient,
  ) {}

  /**
   * Pulls the live catalog from DataSika and upserts it into our own Product table.
   * - New products get a default markup applied to costPrice.
   * - Existing products keep your custom sellPrice/agentPrice, but costPrice and
   *   availability are always refreshed to match DataSika's current values.
   * Run this on a schedule (see queue/catalog-sync.processor.ts) so pricing drift
   * and disabled services never silently break your storefront.
   */
  async syncCatalog() {
    const catalog = await this.dataSika.getCatalog();
    let created = 0;
    let updated = 0;

    const sections: Array<[keyof typeof catalog.services, ServiceType]> = [
      ['data_bundles', ServiceType.DATA_BUNDLE],
      ['mtn_express', ServiceType.MTN_EXPRESS],
    ];

    for (const [key, serviceType] of sections) {
      const section = catalog.services[key];
      if (!section) continue;

      for (const item of section.items) {
        const existing = await this.prisma.product.findUnique({
          where: { dataSikaId: item.product_id },
        });

        if (existing) {
          await this.prisma.product.update({
            where: { id: existing.id },
            data: {
              costPrice: item.price,
              network: item.network,
              bundleGb: item.bundle_gb,
              isAvailable: section.available,
            },
          });
          updated++;
        } else {
          const sellPrice = +(item.price * (1 + DEFAULT_MARKUP_PERCENT / 100)).toFixed(2);
          await this.prisma.product.create({
            data: {
              dataSikaId: item.product_id,
              network: item.network,
              bundleGb: item.bundle_gb,
              costPrice: item.price,
              sellPrice,
              serviceType,
              isAvailable: section.available,
            },
          });
          created++;
        }
      }
    }

    this.logger.log(`Catalog sync complete: ${created} created, ${updated} updated`);
    return { created, updated };
  }

  async listStorefront(role: 'CUSTOMER' | 'AGENT' = 'CUSTOMER') {
    const products = await this.prisma.product.findMany({
      where: { isAvailable: true },
      orderBy: [{ network: 'asc' }, { bundleGb: 'asc' }],
    });

    return products.map((p) => ({
      id: p.id,
      network: p.network,
      bundleGb: p.bundleGb,
      serviceType: p.serviceType,
      price: role === 'AGENT' && p.agentPrice ? p.agentPrice : p.sellPrice,
    }));
  }

  /** Full product rows including cost price — for the admin pricing editor only. */
  async listAll() {
    return this.prisma.product.findMany({
      orderBy: [{ network: 'asc' }, { bundleGb: 'asc' }],
    });
  }

  async setPricing(productId: string, sellPrice: number, agentPrice?: number) {
    return this.prisma.product.update({
      where: { id: productId },
      data: { sellPrice, agentPrice },
    });
  }

  async findById(id: string) {
    return this.prisma.product.findUniqueOrThrow({ where: { id } });
  }
}
