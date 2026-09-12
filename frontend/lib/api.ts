import axios from 'axios';

export const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:4000/api';

export const api = axios.create({ baseURL: API_BASE });

// Attach the JWT (if the user is logged in) to every request.
api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export interface Product {
  id: string;
  network: string;
  bundleGb: string;
  serviceType: 'DATA_BUNDLE' | 'MTN_EXPRESS' | 'RESULT_CHECKER';
  price: string;
  retailPrice: string;
  hasAgentPrice: boolean;
}

export interface OrderTrackingInfo {
  id: string;
  status: 'PENDING' | 'PROCESSING' | 'DELIVERED' | 'FAILED' | 'REFUNDED' | 'REFUND_PROCESSING';
  network: string;
  bundleGb: string;
  recipient: string;
  createdAt: string;
  updatedAt: string;
}
