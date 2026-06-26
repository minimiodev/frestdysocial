/**
 * Helper to resolve image url for Avatar, Cover or Post Media
 * Supports both local uploads path and R2 public URL
 */
export function getMediaUrl(filename: string | null | undefined, type: "avatar" | "cover" | "post" = "avatar"): string {
  if (!filename) {
    if (type === "avatar") return "/assets/images/icons/icon-192x192.png";
    if (type === "cover") return "/assets/images/bg-profile-cover.jpg"; // Fallback bg cover
    return "";
  }

  // If already a full URL
  if (
    filename.startsWith("http://") || 
    filename.startsWith("https://") || 
    filename.startsWith("/") || 
    filename.startsWith("data:")
  ) {
    return filename;
  }

  // Relative path mapping
  if (type === "avatar") {
    return `/uploads/avatars/${filename}`;
  }
  if (type === "cover") {
    return `/uploads/covers/${filename}`;
  }
  
  return `/uploads/${filename}`;
}
