import { IsNumber, IsOptional, IsUUID, Max, Min } from 'class-validator';

export class ProductIdDto {
  @IsUUID()
  id: string;
}

export class SetPricingDto {
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0.01)
  @Max(100000)
  sellPrice: number;

  @IsOptional()
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0.01)
  @Max(100000)
  agentPrice?: number;
}
