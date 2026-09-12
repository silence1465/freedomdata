import { IsEmail, IsOptional, IsString, Length, Matches, MaxLength, MinLength } from 'class-validator';

const GHANA_PHONE_REGEX = /^0\d{9}$/;

export class RegisterDto {
  @Matches(GHANA_PHONE_REGEX)
  phone: string;

  @IsOptional()
  @IsEmail()
  @MaxLength(191)
  email?: string;

  @IsString()
  @MinLength(8)
  @MaxLength(72)
  password: string;

  @IsOptional()
  @IsString()
  @Length(1, 100)
  name?: string;
}

export class LoginDto {
  @Matches(GHANA_PHONE_REGEX)
  phone: string;

  @IsString()
  @MinLength(1)
  @MaxLength(72)
  password: string;
}
