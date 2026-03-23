# 募集職種・応募フォームの表示制御

## ★ まずここだけ知ればよい（表示・非表示）

**操作するファイルは 1 つだけです。**

| やること | ファイル |
|---------|----------|
| 募集職種・応募フォームの出し方を変える | **`src/config/recruit.ts`** |

その中の **`recruitConfig`** というオブジェクトの、次の **2 行だけ**を書き換えます。

```ts
export const recruitConfig = {
  openingsMode: "???", // ← 募集職種ブロック全体
  applyMode: "???",    // ← 応募フォームブロック全体
} as const;
```

- **`openingsMode`** … 採用ページの「募集職種」エリア（アンカー `#openings`）
- **`applyMode`** … 採用ページの「応募フォーム」エリア（アンカー `#apply`）

`src/pages/recruit.astro` やコンポーネントを毎回いじる必要はありません（特別な上書きをしない限り）。

---

### 取りうる値（早見）

**`openingsMode`（募集職種）**

| 設定値 | 画面で何が起きるか |
|--------|-------------------|
| `"list"` | microCMS から求人を取り、あれば一覧表示。0件・エラー時は「現在募集中の職種はありません。」 |
| `"no-vacancies"` | API は使わない。見出し＋「現在募集中の職種はありません。」だけ |
| `"hidden"` | 募集職種のブロック自体を**出さない**（ページに `#openings` が無くなる） |

**`applyMode`（応募フォーム）**

| 設定値 | 画面で何が起きるか |
|--------|-------------------|
| `"form"` | 説明文＋**応募フォームを表示** |
| `"closed-notice"` | 見出し＋「受付していません」系の**案内文だけ**（フォームなし） |
| `"hidden"` | 応募フォームのブロック自体を**出さない**（ページに `#apply` が無くなる） |

### コピペ例

**募集していないとき**

```ts
openingsMode: "no-vacancies",
applyMode: "closed-notice",
```

**募集しているとき**

```ts
openingsMode: "list",
applyMode: "form",
```

変更を保存したあと、反映されない場合は **`npm run dev`（astro dev）を一度止めて再起動**してください。

`hidden` にしたときは、フッターやヘッダーの `href="/recruit#openings"` などが空振りになるので、リンクの削除や別 URL への変更を検討してください。

---

## コンポーネント構成（参考）

採用ページの「募集職種」「応募フォーム」は次のコンポーネントに分割されています。

| コンポーネント | 役割 |
|----------------|------|
| `src/components/RecruitOpenings.astro` | 募集職種セクション（`#openings`） |
| `src/components/RecruitApplyForm.astro` | 応募フォームセクション（`#apply`） |

表示の切り替えの詳細は、このドキュメント冒頭の **「★ まずここだけ知ればよい」** を参照してください。

---

## 1. 採用ページでの利用（既存）

`src/pages/recruit.astro` では次のようにしています。

- `openingsMode === "list"` のときだけ `client.getList` で求人を取得
- `<RecruitOpenings jobs={jobs} />` と `<RecruitApplyForm />` を配置

---

## 2. 別ページで再利用する場合

### 最小例

```astro
---
import RecruitOpenings from "../components/RecruitOpenings.astro";
import RecruitApplyForm from "../components/RecruitApplyForm.astro";
import { client, type JobPosting } from "../lib/microcms";
import { recruitConfig } from "../config/recruit";

let jobs: JobPosting[] = [];
if (recruitConfig.openingsMode === "list") {
  try {
    const data = await client.getList<JobPosting>({ endpoint: "jobs" });
    jobs = data.contents;
  } catch {
    jobs = [];
  }
}
---

<RecruitOpenings jobs={jobs} />
<RecruitApplyForm />
```

### ページ単位で設定を上書きしたい場合

コンポーネントに `mode` を渡すと、`recruitConfig` より優先されます。

```astro
<RecruitOpenings jobs={jobs} mode="list" />
<RecruitApplyForm mode="form" />
```

（本番では設定を `recruit.ts` に寄せる方が管理しやすいです。）

---

## 3. スタイル

クラス名は従来どおり `recruit-openings` / `recruit-apply` 系です。スタイルは `src/styles/project/_p-recruit.scss` にあります。受付停止時の文言は `.recruit-apply-closed` です。

---

## 4. microCMS（`list` 利用時）

- 型定義: `src/lib/microcms.ts` の `JobPosting`
- エンドポイント名: `jobs`（`recruit.astro` と同じ）

API キー未設定やエラー時は求人は空配列になり、`list` モードでは「現在募集中の職種はありません。」と表示されます。
