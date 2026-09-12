import Image from 'next/image';

const LOGOS: Record<string, string> = {
  'MTN Flexa': '/images/mtn.png',
  MTN: '/images/mtn.png',
  Telecel: '/images/telecel.png',
  AirtelTigo: '/images/airteltigo.png',
};

export default function NetworkLogo({ network, size = 44 }: { network: string; size?: number }) {
  const source = LOGOS[network];
  if (!source) {
    return (
      <span className="flex shrink-0 items-center justify-center rounded-xl bg-white font-display text-xs font-bold shadow-sm" style={{ width: size, height: size }}>
        {network.slice(0, 2).toUpperCase()}
      </span>
    );
  }

  return (
    <span className="flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1.5 shadow-sm" style={{ width: size, height: size }}>
      <Image src={source} alt={`${network} logo`} width={size} height={size} className="h-full w-full object-contain" />
    </span>
  );
}
