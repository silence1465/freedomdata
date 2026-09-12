import { Body, Controller, Get, Param, Patch, Post, Query, UseGuards } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { Roles } from '../auth/roles.decorator';
import { RolesGuard } from '../auth/roles.guard';
import { ProductsService } from './products.service';
import { ProductIdDto, SetPricingDto } from './products.dto';

@Controller('products')
export class ProductsController {
  constructor(private products: ProductsService) {}

  @Get()
  async list(@Query('role') role?: 'CUSTOMER' | 'AGENT') {
    return this.products.listStorefront(role ?? 'CUSTOMER');
  }

  @Get('admin')
  @UseGuards(AuthGuard('jwt'), RolesGuard)
  @Roles('ADMIN')
  async listAll() {
    return this.products.listAll();
  }

  @Post('sync')
  @UseGuards(AuthGuard('jwt'), RolesGuard)
  @Roles('ADMIN')
  async sync() {
    return this.products.syncCatalog();
  }

  @Patch(':id/pricing')
  @UseGuards(AuthGuard('jwt'), RolesGuard)
  @Roles('ADMIN')
  async setPricing(
    @Param() params: ProductIdDto,
    @Body() body: SetPricingDto,
  ) {
    return this.products.setPricing(params.id, body.sellPrice, body.agentPrice);
  }
}
