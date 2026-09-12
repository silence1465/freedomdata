import { Injectable, ConflictException } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import * as bcrypt from 'bcrypt';

@Injectable()
export class UsersService {
  constructor(private prisma: PrismaService) {}

  async create(params: { phone: string; email?: string; password: string; name?: string }) {
    const existing = await this.prisma.user.findUnique({ where: { phone: params.phone } });
    if (existing) throw new ConflictException('phone_already_registered');

    const passwordHash = await bcrypt.hash(params.password, 10);
    return this.prisma.user.create({
      data: {
        phone: params.phone,
        email: params.email,
        name: params.name,
        passwordHash,
      },
    });
  }

  async findByPhone(phone: string) {
    return this.prisma.user.findUnique({ where: { phone } });
  }

  async findById(id: string) {
    return this.prisma.user.findUniqueOrThrow({ where: { id } });
  }
}
