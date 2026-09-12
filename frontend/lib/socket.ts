'use client';

import { io, Socket } from 'socket.io-client';

const SOCKET_URL = process.env.NEXT_PUBLIC_SOCKET_URL ?? 'http://localhost:4000';

let socket: Socket | null = null;

/** Lazily creates a single shared socket connection for the browser session. */
export function getSocket(): Socket {
  const token = localStorage.getItem('token');
  if (!socket) {
    socket = io(SOCKET_URL, {
      transports: ['websocket'],
      auth: { token },
    });
  } else if ((socket.auth as { token?: string | null }).token !== token) {
    socket.auth = { token };
    socket.disconnect().connect();
  }
  return socket;
}

export function disconnectSocket(): void {
  socket?.disconnect();
  socket = null;
}
