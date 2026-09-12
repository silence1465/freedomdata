import { IsNumber, Max, Min } from 'class-validator';

export class InitiateTopupDto {
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(1)
  @Max(100000)
  amount: number;
}
