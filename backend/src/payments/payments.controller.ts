import { Body, Controller, Headers, Post, Req, UseGuards, BadRequestException, RawBodyRequest } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { ConfigService } from '@nestjs/config';
import * as crypto from 'crypto';
import axios from 'axios';
import { WalletService } from '../wallet/wallet.service';
import { PrismaService } from '../prisma/prisma.service';
import { LedgerType, Prisma } from '@prisma/client';
import { InitiateTopupDto } from './payments.dto';

@Controller('payments')
export class PaymentsController {
  constructor(
    private wallet: WalletService,
    private prisma: PrismaService,
    private config: ConfigService,
  ) {}

  /** Kicks off a Paystack transaction; frontend redirects the customer to authorization_url. */
  @Post('topup/initiate')
  @UseGuards(AuthGuard('jwt'))
  async initiate(@Req() req: any, @Body() body: InitiateTopupDto) {
    const user = await this.prisma.user.findUniqueOrThrow({ where: { id: req.user.userId } });

    const res = await axios.post(
      'https://api.paystack.co/transaction/initialize',
      {
        email: user.email ?? `${user.phone}@datasika-reseller.local`,
        amount: Math.round(body.amount * 100), // Paystack expects amount in pesewas/kobo
        metadata: { userId: user.id },
      },
      {
        headers: { Authorization: `Bearer ${this.config.get('PAYSTACK_SECRET_KEY')}` },
      },
    );

    return res.data.data; // { authorization_url, access_code, reference }
  }

  /**
   * Paystack webhook — this is the source of truth for crediting wallets, not the
   * frontend redirect callback, since the redirect can be closed/interrupted.
   * Verifies the x-paystack-signature HMAC before trusting the payload.
   */
  @Post('webhook/paystack')
  async webhook(@Headers('x-paystack-signature') signature: string, @Req() req: RawBodyRequest<Request>) {
    const secret = this.config.get<string>('PAYSTACK_SECRET_KEY')!;
    const rawBody = req.rawBody;
    if (!rawBody) throw new BadRequestException('missing_raw_body');

    const expected = crypto.createHmac('sha512', secret).update(rawBody).digest('hex');
    if (expected !== signature) {
      throw new BadRequestException('invalid_signature');
    }

    const event = JSON.parse(rawBody.toString());

    if (event.event === 'charge.success') {
      const userId = event.data.metadata?.userId;
      const amount = event.data.amount / 100;
      const reference = event.data.reference;

      if (!userId || !reference || !Number.isFinite(amount) || amount <= 0) {
        throw new BadRequestException('invalid_payment_payload');
      }

      // Idempotency: skip if we've already recorded this Paystack reference.
      try {
        await this.wallet.credit(userId, amount, LedgerType.TOPUP, { reference });
      } catch (error) {
        if (!(error instanceof Prisma.PrismaClientKnownRequestError && error.code === 'P2002')) {
          throw error;
        }
      }
    }

    return { received: true };
  }
}
