import { Module } from '@nestjs/common';
import { DataSikaClient } from './datasika.client';

@Module({
  providers: [DataSikaClient],
  exports: [DataSikaClient],
})
export class DataSikaModule {}
