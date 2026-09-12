import { Module } from '@nestjs/common';
import { ProductsService } from './products.service';
import { ProductsController } from './products.controller';
import { DataSikaModule } from '../datasika/datasika.module';
import { AuthModule } from '../auth/auth.module';
import { OptionalJwtAuthGuard } from '../auth/optional-jwt.guard';

@Module({
  imports: [DataSikaModule, AuthModule],
  providers: [ProductsService, OptionalJwtAuthGuard],
  controllers: [ProductsController],
  exports: [ProductsService],
})
export class ProductsModule {}
