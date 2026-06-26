import { NextRequest, NextResponse } from "next/server";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const urlParam = req.nextUrl.searchParams.get("url");
    if (!urlParam) {
      return NextResponse.json({ error: "Không tìm thấy URL." }, { status: 400 });
    }

    let targetUrl = urlParam.trim();
    if (!/^https?:\/\//i.test(targetUrl)) {
      targetUrl = "http://" + targetUrl;
    }

    // Fetch trang web
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 3000); // Timeout sau 3s

    const res = await fetch(targetUrl, {
      signal: controller.signal,
      headers: {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
      },
    });
    
    clearTimeout(timeoutId);

    if (!res.ok) {
      return NextResponse.json({ error: "Không thể fetch URL." }, { status: 400 });
    }

    const html = await res.text();

    // Regex trích xuất metadata cơ bản (nhẹ nhàng, Serverless friendly)
    const titleRegex = /<title[^>]*>([^<]+)<\/title>/i;
    const ogTitleRegex = /<meta[^>]+property=["']og:title["'][^>]+content=["']([^"']+)["']/i;
    const ogTitleRegexAlt = /<meta[^>]+content=["']([^"']+)["'][^>]+property=["']og:title["']/i;
    
    const descRegex = /<meta[^>]+name=["']description["'][^>]+content=["']([^"']+)["']/i;
    const ogDescRegex = /<meta[^>]+property=["']og:description["'][^>]+content=["']([^"']+)["']/i;
    const ogDescRegexAlt = /<meta[^>]+content=["']([^"']+)["'][^>]+property=["']og:description["']/i;

    const ogImageRegex = /<meta[^>]+property=["']og:image["'][^>]+content=["']([^"']+)["']/i;
    const ogImageRegexAlt = /<meta[^>]+content=["']([^"']+)["'][^>]+property=["']og:image["']/i;

    let title = "";
    const titleMatch = html.match(titleRegex);
    const ogTitleMatch = html.match(ogTitleRegex) || html.match(ogTitleRegexAlt);
    if (ogTitleMatch) title = ogTitleMatch[1];
    else if (titleMatch) title = titleMatch[1];

    let desc = "";
    const descMatch = html.match(descRegex);
    const ogDescMatch = html.match(ogDescRegex) || html.match(ogDescRegexAlt);
    if (ogDescMatch) desc = ogDescMatch[1];
    else if (descMatch) desc = descMatch[1];

    let image = "";
    const ogImageMatch = html.match(ogImageRegex) || html.match(ogImageRegexAlt);
    if (ogImageMatch) image = ogImageMatch[1];

    // Decode HTML entities cơ bản
    const decodeHtml = (str: string) => {
      return str
        .replace(/&quot;/g, '"')
        .replace(/&amp;/g, "&")
        .replace(/&lt;/g, "<")
        .replace(/&gt;/g, ">")
        .replace(/&#39;/g, "'")
        .replace(/&rsquo;/g, "'");
    };

    return NextResponse.json({
      title: decodeHtml(title.trim()),
      desc: decodeHtml(desc.trim()),
      image: image.trim(),
      url: targetUrl,
    });
  } catch (error) {
    console.error("Preview URL Link error:", error);
    return NextResponse.json({ error: "Lỗi tạo preview link" }, { status: 500 });
  }
}
