import { Injectable } from '@nestjs/common';
import { PassportStrategy } from '@nestjs/passport';
import { ExtractJwt, Strategy } from 'passport-jwt';
import { ConfigService } from '@nestjs/config';

@Injectable()
export class JwtStrategy extends PassportStrategy(Strategy) {
  constructor(config: ConfigService) {
    const secret = config.get<string>('JWT_SECRET');
    if (!secret) {
      throw new Error('JWT_SECRET must be configured');
    }
    if (
      config.get<string>('NODE_ENV') === 'production' &&
      (secret.length < 32 || secret === 'change_this_to_a_long_random_string')
    ) {
      throw new Error('JWT_SECRET must contain at least 32 unique characters in production');
    }
    super({
      jwtFromRequest: ExtractJwt.fromAuthHeaderAsBearerToken(),
      ignoreExpiration: false,
      secretOrKey: secret,
    });
  }

  async validate(payload: { sub: string; role: string }) {
    return { userId: payload.sub, role: payload.role };
  }
}
