import { Injectable, Logger, HttpException, HttpStatus } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosInstance, AxiosError } from 'axios';

export function isDefinitiveDataSikaRejection(err: unknown): boolean {
  const status = (err as any)?.getStatus?.();
  return typeof status === 'number' && status >= 400 && status < 500 && ![408, 409, 425, 429].includes(status);
}

export interface DataSikaCatalogItem {
  product_id: string;
  network: string;
  bundle_gb: number;
  price: number;
  currency: string;
  validity: string;
}

export interface DataSikaCatalogResponse {
  services: {
    data_bundles?: { available: boolean; items: DataSikaCatalogItem[] };
    mtn_express?: { available: boolean; items: DataSikaCatalogItem[] };
    result_checkers?: { available: boolean; items: any[] };
  };
  enabled_services: string[];
}

export interface DataSikaBuyResponse {
  order_id: string;
  status: string;
  network: string;
  bundle_gb: number;
  recipient: string;
  amount_charged: number;
  new_balance: number;
}

export interface DataSikaOrderStatusResponse {
  order_id: string;
  status: 'pending' | 'processing' | 'delivered' | 'failed' | 'refunded' | 'refund_processing';
  network: string;
  bundle_gb: number;
  recipient: string;
  amount_charged: number;
}

/**
 * Thin wrapper around the DataSika Developer API v2.
 * Docs: see datasika-api-data_bundles.md
 *
 * NOTE: /api-buy-express is referenced in the docs (error code `use_express_endpoint`)
 * but its request/response shape isn't documented. The buyExpress() method below
 * mirrors buyData()'s shape as a best-effort placeholder — verify against DataSika's
 * actual Express docs/support before relying on it in production.
 */
@Injectable()
export class DataSikaClient {
  private readonly logger = new Logger(DataSikaClient.name);
  private readonly http: AxiosInstance;

  constructor(private config: ConfigService) {
    this.http = axios.create({
      baseURL: this.config.get<string>('DATASIKA_BASE_URL'),
      timeout: 20_000,
      headers: {
        Authorization: `Bearer ${this.config.get<string>('DATASIKA_API_KEY')}`,
      },
    });
  }

  async getCatalog(): Promise<DataSikaCatalogResponse> {
    return this.request<DataSikaCatalogResponse>('get', '/api-catalog');
  }

  async buyData(params: {
    product_id: string;
    recipient: string;
    idempotencyKey: string;
  }): Promise<DataSikaBuyResponse> {
    return this.request<DataSikaBuyResponse>('post', '/api-buy-data', {
      product_id: params.product_id,
      recipient: params.recipient,
    }, {
      'Idempotency-Key': params.idempotencyKey,
    });
  }

  async buyExpress(params: {
    product_id: string;
    recipient: string;
    idempotencyKey: string;
  }): Promise<DataSikaBuyResponse> {
    return this.request<DataSikaBuyResponse>('post', '/api-buy-express', {
      product_id: params.product_id,
      recipient: params.recipient,
    }, {
      'Idempotency-Key': params.idempotencyKey,
    });
  }

  async getOrderStatus(orderId: string): Promise<DataSikaOrderStatusResponse> {
    return this.request<DataSikaOrderStatusResponse>(
      'get',
      `/api-order-status?order_id=${encodeURIComponent(orderId)}`,
    );
  }

  private async request<T>(
    method: 'get' | 'post',
    path: string,
    data?: any,
    extraHeaders?: Record<string, string>,
  ): Promise<T> {
    try {
      const res = await this.http.request<T>({
        method,
        url: path,
        data,
        headers: {
          'Content-Type': 'application/json',
          ...extraHeaders,
        },
        // 202 is a valid "accepted" response for Express dispatch per the docs
        validateStatus: (status) => status === 200 || status === 202,
      });
      return res.data;
    } catch (err) {
      const axiosErr = err as AxiosError<any>;

      if (axiosErr.response) {
        const status = axiosErr.response.status;
        const body = axiosErr.response.data;

        // 429: surface retry_after so the caller (BullMQ processor) can back off correctly
        if (status === 429) {
          this.logger.warn(`DataSika rate limited: retry_after=${body?.retry_after}`);
        }

        this.logger.error(`DataSika ${method.toUpperCase()} ${path} -> ${status}: ${JSON.stringify(body)}`);
        throw new HttpException(
          {
            source: 'datasika',
            code: body?.error || body?.code || 'unknown_error',
            message: body?.message || 'DataSika API error',
            retry_after: body?.retry_after,
            refunded: body?.refunded,
          },
          status,
        );
      }

      this.logger.error(`DataSika network error on ${method.toUpperCase()} ${path}: ${axiosErr.message}`);
      throw new HttpException(
        { source: 'datasika', code: 'network_error', message: axiosErr.message },
        HttpStatus.BAD_GATEWAY,
      );
    }
  }
}
