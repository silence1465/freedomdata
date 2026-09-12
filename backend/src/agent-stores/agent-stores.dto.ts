import { IsEnum, IsOptional, IsString, IsUUID, Matches, MaxLength } from 'class-validator';

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
