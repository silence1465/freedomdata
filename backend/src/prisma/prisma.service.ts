import { Injectable, OnModuleInit, OnModuleDestroy } from '@nestjs/common';
import { PrismaClient } from '@prisma/client';
import { PrismaMariaDb } from '@prisma/adapter-mariadb';

/**
 * Prisma 7 requires a driver adapter to be passed to the PrismaClient constructor —
 * `new PrismaClient()` with a schema-level `url` is no longer supported. We parse
 * DATABASE_URL once here so the app only needs the single connection string in .env,
 * matching prisma.config.ts (which the CLI/migrate commands read separately).
 */
function adapterFromDatabaseUrl(): PrismaMariaDb {
  const raw = process.env.DATABASE_URL;
  if (!raw) {
    throw new Error('DATABASE_URL is not set — check your .env file');
  }

  const url = new URL(raw);
  return new PrismaMariaDb({
    host: url.hostname,
    port: url.port ? Number(url.port) : 3306,
    user: decodeURIComponent(url.username),
    password: decodeURIComponent(url.password),
    database: url.pathname.replace(/^\//, ''),
    connectionLimit: 5,
  });
}

@Injectable()
export class PrismaService extends PrismaClient implements OnModuleInit, OnModuleDestroy {
  constructor() {
    super({ adapter: adapterFromDatabaseUrl() });
  }

  async onModuleInit() {
    await this.$connect();
  }

  async onModuleDestroy() {
    await this.$disconnect();
  }
}
