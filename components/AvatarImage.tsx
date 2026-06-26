"use client";

import { getMediaUrl } from "@/lib/utils";

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
  const resolvedSrc = src && src.includes("http") ? src.substring(src.indexOf("http")) : getMediaUrl(src, "avatar");

  return (
    <img
      src={resolvedSrc}
      alt={alt}
      className={className}
      onError={(e) => {
        e.currentTarget.src = fallback;
        e.currentTarget.onerror = null;
      }}
    />
  );
}

