import { NestFactory } from '@nestjs/core';
import { ValidationPipe } from '@nestjs/common';
import { AppModule } from './app.module';

async function bootstrap() {
  const app = await NestFactory.create(AppModule, { rawBody: true });

  const frontendUrl = process.env.FRONTEND_URL;
  if (process.env.NODE_ENV === 'production' && !frontendUrl) {
    throw new Error('FRONTEND_URL must be configured in production');
  }
  app.enableCors({ origin: frontendUrl ?? '*' });
  app.useGlobalPipes(new ValidationPipe({ whitelist: true, transform: true }));
  app.setGlobalPrefix('api');

  const port = process.env.PORT ?? 4000;
  await app.listen(port);
  console.log(`DataSika reseller API listening on :${port}`);
}
bootstrap();
