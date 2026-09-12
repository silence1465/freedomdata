import { Injectable, UnauthorizedException } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import * as bcrypt from 'bcrypt';
import { UsersService } from '../users/users.service';

@Injectable()
export class AuthService {
  constructor(
    private users: UsersService,
    private jwt: JwtService,
  ) {}

  async register(params: { phone: string; email?: string; password: string; name?: string }) {
    const user = await this.users.create(params);
    return this.issueToken(user.id, user.role);
  }

  async login(phone: string, password: string) {
    const user = await this.users.findByPhone(phone);
    if (!user || !(await bcrypt.compare(password, user.passwordHash))) {
      throw new UnauthorizedException('invalid_credentials');
    }
    return this.issueToken(user.id, user.role);
  }

  private issueToken(userId: string, role: string) {
    return {
      access_token: this.jwt.sign({ sub: userId, role }),
      userId,
      role,
    };
  }
}
