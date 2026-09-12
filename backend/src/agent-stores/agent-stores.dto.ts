import { IsEnum, IsOptional, IsString, IsUrl, IsUUID, Matches, MaxLength, ValidateNested } from 'class-validator';
import { Type } from 'class-transformer';

export class SubmitAgentStoreOrderDto {
  @IsUUID()
  productId: string;

  @Matches(/^0\d{9}$/)
  recipient: string;

  @IsOptional()
  @IsString()
  @MaxLength(100)
  customerName?: string;

  @IsOptional()
  @Matches(/^0\d{9}$/)
  customerPhone?: string;

  @IsOptional()
  @IsString()
  @MaxLength(191)
  transactionId?: string;
}

export enum AgentStoreReviewAction {
  APPROVE = 'APPROVE',
  HOLD = 'HOLD',
  REJECT = 'REJECT',
}

export class ReviewAgentStoreOrderDto {
  @IsEnum(AgentStoreReviewAction)
  action: AgentStoreReviewAction;

  @IsOptional()
  @IsString()
  @MaxLength(191)
  note?: string;
}

class PushSubscriptionKeysDto {
  @IsString()
  p256dh: string;

  @IsString()
  auth: string;
}

export class SavePushSubscriptionDto {
  @IsUrl({ require_tld: false })
  endpoint: string;

  @ValidateNested()
  @Type(() => PushSubscriptionKeysDto)
  keys: PushSubscriptionKeysDto;
}

export class RemovePushSubscriptionDto {
  @IsUrl({ require_tld: false })
  endpoint: string;
}
