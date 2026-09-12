import { Body, Controller, Get, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { OrdersService } from './orders.service';
import { CreateOrderDto } from './orders.dto';

@Controller('orders')
export class OrdersController {
  constructor(private orders: OrdersService) {}

  @Post()
  @UseGuards(AuthGuard('jwt'))
  async create(@Req() req: any, @Body() body: CreateOrderDto) {
    return this.orders.placeOrder(req.user.userId, body.productId, body.recipient);
  }

  @Get('mine')
  @UseGuards(AuthGuard('jwt'))
  async mine(@Req() req: any) {
    return this.orders.listForUser(req.user.userId);
  }

  // Public lookup for the tracking page — no auth required, matches how DataSika
  // itself treats order_id as the lookup key. Don't leak more than status/basic info.
  @Get(':id')
  async trackOne(@Param('id') id: string) {
    const order = await this.orders.getOrder(id);
    return {
      id: order.id,
      status: order.status,
      network: order.product.network,
      bundleGb: order.product.bundleGb,
      recipient: `${order.recipient.slice(0, 3)}****${order.recipient.slice(-3)}`,
      createdAt: order.createdAt,
      updatedAt: order.updatedAt,
    };
  }
}
