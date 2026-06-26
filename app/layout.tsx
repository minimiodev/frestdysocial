import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "@/app/globals.css";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import BottomNav from "@/components/BottomNav";
import { cookies } from "next/headers";
import { verifyToken } from "@/lib/auth";
import { db } from "@/lib/db";
import { SWRConfig } from "swr";

const inter = Inter({ subsets: ["latin"] });

export const metadata: Metadata = {
  title: "Frest - Siêu mạng xã hội tối giản thế hệ mới",
  description: "Frest là mạng xã hội chia sẻ hình ảnh, video, chat realtime siêu mượt và bảo mật.",
  icons: "/assets/images/icons/icon-192x192.png",
};

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  // Lấy dữ liệu user trên server-side để tăng tốc độ load trang
  let currentUser = null;
  let currentIdentity = null;
  let myPages: any[] = [];
  let isAdmin = false;

  const cookieStore = cookies();
  const token = cookieStore.get("frest_session")?.value;
  const adminToken = cookieStore.get("frest_admin_session")?.value;

  if (token) {
    const decoded = verifyToken(token);
    if (decoded) {
      currentUser = await db.user.findUnique({
        where: { id: decoded.userId },
        select: {
          id: true,
          username: true,
          fullName: true,
          avatarFilename: true,
          verificationType: true,
          isAdult: true,
        },
      });

      if (currentUser) {
        // Lấy danh sách trang sở hữu
        myPages = await db.page.findMany({
          where: { ownerId: currentUser.id },
          select: {
            id: true,
            pageName: true,
            pageUsername: true,
            avatarFilename: true,
            isVerified: true,
          },
        });

        // Mặc định identity là User cá nhân
        currentIdentity = {
          type: "user",
          id: currentUser.id,
          name: currentUser.fullName || currentUser.username,
          avatar: currentUser.avatarFilename,
          username: currentUser.username,
          verificationType: currentUser.verificationType,
        };

        // Đọc cookie identity đã lưu nếu có để đổi vai trò hoạt động
        const identityCookie = cookieStore.get("frest_identity")?.value;
        if (identityCookie) {
          try {
            const parsed = JSON.parse(decodeURIComponent(identityCookie));
            if (parsed.type === "page" && parsed.id) {
              const matchedPage = myPages.find((p) => p.id === parsed.id);
              if (matchedPage) {
                currentIdentity = {
                  type: "page",
                  id: matchedPage.id,
                  name: matchedPage.pageName,
                  avatar: matchedPage.avatarFilename,
                  username: matchedPage.pageUsername,
                  verificationType: matchedPage.isVerified ? "official" : null,
                };
              }
            }
          } catch (e) {}
        }
      }
    }
  }

  // Check admin role
  if (adminToken) {
    try {
      const decodedAdmin = verifyToken(adminToken) as any;
      if (decodedAdmin && decodedAdmin.adminId) {
        isAdmin = true;
      }
    } catch (e) {}
  }

  return (
    <html lang="vi" className="dark">
      <body className={`${inter.className} min-h-screen flex`}>
        <SWRConfig value={{ revalidateOnFocus: false, revalidateOnReconnect: false, dedupingInterval: 4000 }}>
          {/* Sidebar */}
          <Sidebar currentUser={currentUser} isAdmin={isAdmin} />

          {/* Content Container */}
          <div className="flex-1 md:ml-[var(--sidebar-width)] flex flex-col min-h-screen pb-16 md:pb-0">
            {/* Header */}
            <Header currentIdentity={currentIdentity} myPages={myPages} />

            {/* Main Area */}
            <main className="flex-1 p-4 md:p-6 max-w-6xl mx-auto w-full">
              {children}
            </main>
          </div>

          {/* Bottom Navigation for Mobile */}
          <BottomNav />
        </SWRConfig>
      </body>
    </html>
  );
}
