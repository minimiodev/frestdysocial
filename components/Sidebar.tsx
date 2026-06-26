"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import useSWR from "swr";
import { 
  Home, 
  MessageSquare, 
  Bookmark, 
  User, 
  ShieldAlert, 
  LogOut, 
  Sparkles,
  Compass,
  Bell
} from "lucide-react";

const fetcher = (url: string) => fetch(url).then((res) => res.json());

interface SidebarProps {
  currentUser?: {
    username: string;
    fullName: string;
    avatarFilename: string;
    verificationType?: string | null;
  } | null;
  isAdmin?: boolean;
}

export default function Sidebar({ currentUser, isAdmin }: SidebarProps) {
  const pathname = usePathname();
  const router = useRouter();

  // Lấy danh sách thông báo để tính badge động
  const { data: notificationsData } = useSWR(
    currentUser ? "/api/notifications" : null,
    fetcher,
    { refreshInterval: 15000 }
  );

  const unreadNotifications = notificationsData?.notifications?.filter(
    (n: any) => !n.isRead
  )?.length || 0;

  const menuItems = [
    { name: "Trang chủ", href: "/", icon: Home },
    { name: "Khám phá", href: "/explore", icon: Compass },
    { name: "Hộp thư", href: "/chat", icon: MessageSquare },
    { 
      name: "Thông báo", 
      href: "/notifications", 
      icon: Bell, 
      badge: unreadNotifications 
    },
    { name: "Dấu trang", href: "/bookmarks", icon: Bookmark },
  ];

  if (currentUser) {
    menuItems.push({
      name: "Trang cá nhân",
      href: `/profile/${currentUser.username}`,
      icon: User,
    });
  }

  if (isAdmin) {
    menuItems.push({
      name: "Quản trị",
      href: "/admin",
      icon: ShieldAlert,
    });
  }

  const handleLogout = async () => {
    try {
      await fetch("/api/auth/logout", { method: "POST" });
      router.push("/login");
      router.refresh();
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <aside className="fixed left-0 top-0 bottom-0 w-[var(--sidebar-width)] bg-[var(--card-bg)] border-r border-[var(--card-border)] p-6 flex flex-col justify-between hidden md:flex z-40">
      <div>
        {/* Logo */}
        <Link href="/" className="flex items-center gap-3 mb-8 group">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-accent-purple flex items-center justify-center shadow-premium transform group-hover:scale-105 transition-all">
            <Sparkles className="w-5 h-5 text-white" />
          </div>
          <div>
            <h1 className="font-extrabold text-xl tracking-tight bg-gradient-to-r from-primary to-accent-purple bg-clip-text text-transparent">
              Frest
            </h1>
            <p className="text-[10px] text-gray-400 font-medium">NEXT GEN SOCIAL</p>
          </div>
        </Link>

        {/* Menu Navigation */}
        <nav className="space-y-1.5">
          {menuItems.map((item) => {
            const Icon = item.icon;
            const isActive = pathname === item.href;
            return (
              <Link
                key={item.name}
                href={item.href}
                className={`flex items-center justify-between px-4 py-3 rounded-xl font-medium text-sm transition-all duration-200 group ${
                  isActive
                    ? "bg-primary text-white shadow-premium"
                    : "text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#202024] hover:text-primary dark:hover:text-white"
                }`}
              >
                <div className="flex items-center gap-3.5">
                  <Icon className={`w-5 h-5 transition-transform duration-200 group-hover:scale-110 ${isActive ? "text-white" : "text-gray-400 group-hover:text-primary"}`} />
                  <span>{item.name}</span>
                </div>
                {item.badge && item.badge > 0 && !isActive && (
                  <span className="bg-accent-pink text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold">
                    {item.badge}
                  </span>
                )}
              </Link>
            );
          })}
        </nav>
      </div>

      {/* User Info / Logout */}
      {currentUser ? (
        <div className="border-t border-[var(--card-border)] pt-4 flex flex-col gap-3">
          <div className="flex items-center gap-3 px-2">
            <img
              src={`/uploads/avatars/${currentUser.avatarFilename}`}
              alt={currentUser.fullName}
              className="w-10 h-10 rounded-xl object-cover ring-2 ring-primary/20"
              onError={(e) => {
                e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
              }}
            />
            <div className="overflow-hidden">
              <p className="font-semibold text-sm truncate flex items-center gap-0.5">
                <span>{currentUser.fullName}</span>
                {currentUser.verificationType === "official" && (
                  <svg className="w-3.5 h-3.5 inline-block shrink-0" viewBox="0 0 24 24" title="Đã xác minh">
                    <g fillRule="evenodd" transform="translate(-92)">
                      <path fill="#1877f2" d="m115.887 14.475-1.269-2.475 1.267-2.474a1.02 1.02 0 0 0-.355-1.326l-2.334-1.51-.14-2.775a1.018 1.018 0 0 0-.97-.971l-2.778-.14-1.51-2.336a1.02 1.02 0 0 0-1.324-.354L104 1.38 101.526.114a1.02 1.02 0 0 0-1.326.354l-1.509 2.336-2.777.14a1.017 1.017 0 0 0-.97.97l-.14 2.777L92.468 8.2a1.02 1.02 0 0 0-.354 1.325L93.382 12l-1.268 2.474a1.02 1.02 0 0 0 .355 1.326l2.335 1.509.14 2.776c.025.528.443.945.97.971l2.777.14 1.51 2.336a1.02 1.02 0 0 0 1.324.354L104 22.62l2.474 1.267c.469.242 1.039.09 1.326-.355l1.51-2.335 2.776-.14c.527-.026.945-.443.97-.97l.14-2.777 2.336-1.51c.443-.286.595-.856.354-1.324" />
                      <path fill="#ffffff" d="m109.207 9.707-6.5 6.5a.996.996 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L102 14.086l5.793-5.793a1 1 0 1 1 1.414 1.414" />
                    </g>
                  </svg>
                )}
              </p>
              <p className="text-[11px] text-gray-400 truncate">@{currentUser.username}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="flex items-center gap-3.5 w-full px-4 py-2.5 rounded-xl font-medium text-sm text-accent-pink hover:bg-red-50 dark:hover:bg-red-950/20 transition-all duration-200 group"
          >
            <LogOut className="w-5 h-5 transition-transform duration-200 group-hover:translate-x-0.5" />
            <span>Đăng xuất</span>
          </button>
        </div>
      ) : (
        <div className="flex flex-col gap-2">
          <Link
            href="/login"
            className="flex items-center justify-center w-full px-4 py-2.5 rounded-xl font-semibold text-sm bg-primary text-white hover:bg-primary-hover shadow-premium transition-all text-center"
          >
            Đăng nhập
          </Link>
        </div>
      )}
    </aside>
  );
}
