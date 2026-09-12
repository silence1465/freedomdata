import { Body, Controller, Get, Param, Patch, UseGuards } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { Roles } from '../auth/roles.decorator';
import { RolesGuard } from '../auth/roles.guard';
import { AdminAgentsService } from './admin-agents.service';
import { ExtendAgentSubscriptionDto } from './admin-agents.dto';

@Controller('admin/agents')
@UseGuards(AuthGuard('jwt'), RolesGuard)
@Roles('ADMIN')
export class AdminAgentsController {
  constructor(private agents: AdminAgentsService) {}

  @Get()
  list() { return this.agents.list(); }

  @Patch(':id/subscription')
  extend(@Param('id') id: string, @Body() body: ExtendAgentSubscriptionDto) {
    return this.agents.extend(id, body.days);
  }
}
