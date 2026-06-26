"use client";

interface AvatarImageProps {
  src: string;
  alt: string;
  className?: string;
  fallback?: string;
}

export default function AvatarImage({
  src,
  alt,
  className = "",
  fallback = "/assets/images/icons/icon-192x192.png",
}: AvatarImageProps) {
  return (
    <img
      src={src}
      alt={alt}
      className={className}
      onError={(e) => {
        e.currentTarget.src = fallback;
        e.currentTarget.onerror = null;
      }}
    />
  );
}
