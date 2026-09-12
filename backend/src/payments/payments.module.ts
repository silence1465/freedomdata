import { Module } from '@nestjs/common';
import { PaymentsController } from './payments.controller';
import { WalletModule } from '../wallet/wallet.module';

@Module({
  imports: [WalletModule],
  controllers: [PaymentsController],
})
export class PaymentsModule {}
