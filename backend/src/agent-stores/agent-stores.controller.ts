import { Body, Controller, Get, Param, Patch, Post, Req, Res, UploadedFile, UseGuards, UseInterceptors } from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { AuthGuard } from '@nestjs/passport';
import { Response } from 'express';
import { Roles } from '../auth/roles.decorator';
import { RolesGuard } from '../auth/roles.guard';
import { ReviewAgentStoreOrderDto, SubmitAgentStoreOrderDto } from './agent-stores.dto';
import { AgentStoresService } from './agent-stores.service';

@Controller('agent-stores')
export class AgentStoresController {
  constructor(private stores: AgentStoresService) {}

  @Get('me')
  @UseGuards(AuthGuard('jwt'), RolesGuard)
  @Roles('AGENT')
  dashboard(@Req() req: any) {
    return this.stores.dashboard(req.user.userId);
  }

  @Get('orders/:id/proof')
  @UseGuards(AuthGuard('jwt'), RolesGuard)
  @Roles('AGENT')
  async proof(@Req() req: any, @Param('id') id: string, @Res() response: Response) {
    const proof = await this.stores.proof(req.user.userId, id);
    response.type(proof.mime).send(proof.data);
  }

  @Patch('orders/:id')
  @UseGuards(AuthGuard('jwt'), RolesGuard)
  @Roles('AGENT')
  review(@Req() req: any, @Param('id') id: string, @Body() body: ReviewAgentStoreOrderDto) {
    return this.stores.review(req.user.userId, id, body);
  }

  @Get(':code')
  store(@Param('code') code: string) {
    return this.stores.publicStore(code);
  }

  @Post(':code/orders')
  @UseInterceptors(FileInterceptor('proof', { limits: { fileSize: 5 * 1024 * 1024, files: 1 } }))
  submit(@Param('code') code: string, @Body() body: SubmitAgentStoreOrderDto, @UploadedFile() file?: any) {
    return this.stores.submit(code, body, file);
  }
}
