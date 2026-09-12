import { IsInt, Max, Min } from 'class-validator';

export class ExtendAgentSubscriptionDto {
  @IsInt()
  @Min(1)
  @Max(3650)
  days: number;
}
