import { IsUUID, Matches } from 'class-validator';

export class CreateOrderDto {
  @IsUUID()
  productId: string;

  @Matches(/^0\d{9}$/)
  recipient: string;
}
