"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { UserCheck, UserPlus, Edit3, Settings } from "lucide-react";

interface PageClientProps {
  isFollowing: boolean;
  pageUsername: string;
  isOwnPage: boolean;
}

export default function PageClient({ isFollowing: initialFollowing, pageUsername, isOwnPage }: PageClientProps) {
  const router = useRouter();
  const [isFollowing, setIsFollowing] = useState(initialFollowing);
  const [loading, setLoading] = useState(false);

  const handleFollowToggle = async () => {
    setLoading(true);
    try {
      // Vì bảng page_follows liên kết userId và pageId, ta sẽ viết một API toggle follow page hoặc call API chung
      // Ở đây ta gọi API follow page. Hãy tạo API /api/pages/[pageUsername]/follow để xử lý.
      const res = await fetch(`/api/pages/${pageUsername}/follow`, {
        method: "POST",
      });

      if (res.ok) {
        const data = await res.json();
        setIsFollowing(data.following);
        router.refresh();
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  if (isOwnPage) {
    return (
      <div className="flex gap-2">
        <button
          onClick={() => router.push("/settings?tab=pages")}
          className="px-4 py-2 border border-[var(--card-border)] hover:bg-gray-50 dark:hover:bg-[#202024] rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5"
        >
          <Edit3 className="w-3.5 h-3.5" />
          Quản lý trang
        </button>
      </div>
    );
  }

  return (
    <button
      onClick={handleFollowToggle}
      disabled={loading}
      className={`px-5 py-2 text-xs font-extrabold rounded-xl transition-all shadow-sm flex items-center gap-1.5 ${
        isFollowing
          ? "bg-gray-100 hover:bg-gray-200 dark:bg-[#202024] dark:hover:bg-[#2a2a30] text-primary"
          : "bg-primary hover:bg-primary-hover text-white"
      }`}
    >
      {isFollowing ? (
        <>
          <UserCheck className="w-4 h-4" />
          <span>Đang theo dõi trang</span>
        </>
      ) : (
        <>
          <UserPlus className="w-4 h-4" />
          <span>Theo dõi trang</span>
        </>
      )}
    </button>
  );
}
