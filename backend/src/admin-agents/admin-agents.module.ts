import { Module } from '@nestjs/common';
import { AuthModule } from '../auth/auth.module';
import { AdminAgentsController } from './admin-agents.controller';
import { AdminAgentsService } from './admin-agents.service';

@Module({
  imports: [AuthModule],
  controllers: [AdminAgentsController],
  providers: [AdminAgentsService],
})
export class AdminAgentsModule {}
