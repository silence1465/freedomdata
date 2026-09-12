import { Controller, Get, UseGuards, Req } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { WalletService } from './wallet.service';

@Controller('wallet')
@UseGuards(AuthGuard('jwt'))
export class WalletController {
  constructor(private wallet: WalletService) {}

  @Get('balance')
  async balance(@Req() req: any) {
    return { balance: await this.wallet.getBalance(req.user.userId) };
  }

  @Get('history')
  async history(@Req() req: any) {
    return this.wallet.history(req.user.userId);
  }
}
