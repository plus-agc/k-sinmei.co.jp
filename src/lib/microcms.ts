import { createClient } from "microcms-js-sdk";

if (
  !import.meta.env.MICROCMS_SERVICE_DOMAIN ||
  !import.meta.env.MICROCMS_API_KEY
) {
  throw new Error(
    "microCMS の環境変数が設定されていません。.env ファイルを確認してください。\n" +
      "MICROCMS_SERVICE_DOMAIN と MICROCMS_API_KEY を設定してください。",
  );
}

export const client = createClient({
  serviceDomain: import.meta.env.MICROCMS_SERVICE_DOMAIN,
  apiKey: import.meta.env.MICROCMS_API_KEY,
});

// ニュースの型定義
export type News = {
  id: string;
  createdAt: string;
  updatedAt: string;
  publishedAt: string;
  title: string;
  content: string;
  category?: string;
};

// 工事実績の型定義
export type Work = {
  id: string;
  createdAt: string;
  updatedAt: string;
  publishedAt: string;
  title: string;
  image: {
    url: string;
    height: number;
    width: number;
  };
  description?: string;
};
