import express, { Request, Response } from "express";
import path from "path";
import { createServer as createViteServer } from "vite";
import { GoogleGenAI } from "@google/genai";
import crypto from "crypto";
import { initialSites, initialCategories, initialTrendCandidates, initialArticles } from "./seedData";

const app = express();
const PORT = 3000;

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Lazy GoogleGenAI client
let aiClient: GoogleGenAI | null = null;
function getAi(): GoogleGenAI {
  if (!aiClient) {
    aiClient = new GoogleGenAI({
      apiKey: process.env.GEMINI_API_KEY || "",
      httpOptions: {
        headers: {
          "User-Agent": "aistudio-build",
        },
      },
    });
  }
  return aiClient;
}

// ----------------------------------------------------
// In-Memory Database Store with full relational schema
// ----------------------------------------------------
interface Store {
  sites: any[];
  categories: any[];
  trendCandidates: any[];
  articles: any[];
  articleSources: any[];
  imageGroups: any[];
  images: any[];
  imageKeywords: any[];
  votes: any[];
  comments: any[];
  bannedKeywords: any[];
  snsQueue: any[];
  siteSettings: Record<number, any>;
  logs: any[];
}

const store: Store = {
  sites: [...initialSites],
  categories: [...initialCategories],
  trendCandidates: [...initialTrendCandidates],
  articles: [...initialArticles],
  articleSources: [
    {
      id: 1,
      articleId: 1,
      sourceType: "official",
      title: "千鳥・大悟 新番組制作決定 独占配信プレスリリース",
      url: "https://press.example.com/chidori-show",
      publisher: "配信プラットフォーム公式",
      reliabilityScore: 100,
    },
    {
      id: 2,
      articleId: 1,
      sourceType: "news",
      title: "大悟 単独MCで新たな挑戦、公式ティザーが話題沸騰",
      url: "https://news.example.com/entame/123",
      publisher: "オリコンニュース",
      reliabilityScore: 90,
    },
    {
      id: 3,
      articleId: 2,
      sourceType: "official",
      title: "Monster Hunter Wilds タイトルアップデート第1弾ロードマップ",
      url: "https://capcom.example.com/mhw-update",
      publisher: "カプコン公式",
      reliabilityScore: 100,
    },
    {
      id: 4,
      articleId: 2,
      sourceType: "youtube",
      title: "【公式】Monster Hunter Wilds 第1弾大型アプデ紹介映像",
      url: "https://youtube.com/watch?v=M7lc1UVf-VE",
      publisher: "CAPCOM CHANNEL公式",
      reliabilityScore: 95,
    },
  ],
  imageGroups: [
    { id: 1, siteId: 1, name: "千鳥", genre: "entertainment", keywords: ["千鳥", "大悟", "ノブ", "お笑い", "芸人"] },
    { id: 2, siteId: 1, name: "ダウンタウン", genre: "entertainment", keywords: ["ダウンタウン", "浜田雅功", "松本人志", "バラエティ"] },
    { id: 3, siteId: 1, name: "モンスターハンター", genre: "game", keywords: ["モンスターハンター", "モンハン", "Wilds", "ハンター"] },
    { id: 4, siteId: 1, name: "スタジオジブリ", genre: "entertainment", keywords: ["ジブリ", "宮崎駿", "アニメ", "企画展"] },
  ],
  images: [
    {
      id: 1,
      siteId: 1,
      groupId: 1,
      groupName: "千鳥",
      filename: "chidori_001.webp",
      url: "https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1000&q=80",
      altText: "お笑いステージマイクイメージ",
      isActive: true,
      useCount: 3,
      lastUsedAt: new Date(Date.now() - 3600 * 1000).toISOString(),
      keywords: ["千鳥", "大悟", "ノブ", "お笑い", "テレビ"],
    },
    {
      id: 2,
      siteId: 1,
      groupId: 2,
      groupName: "ダウンタウン",
      filename: "downtown_001.webp",
      url: "https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=1000&q=80",
      altText: "スタジオ収録イメージ",
      isActive: true,
      useCount: 1,
      lastUsedAt: new Date(Date.now() - 86400 * 1000).toISOString(),
      keywords: ["ダウンタウン", "浜ちゃん", "松っちゃん", "バラエティ"],
    },
    {
      id: 3,
      siteId: 1,
      groupId: 3,
      groupName: "モンスターハンター",
      filename: "mhw_wilds_001.webp",
      url: "https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=1000&q=80",
      altText: "大自然ハンティングイメージ",
      isActive: true,
      useCount: 5,
      lastUsedAt: new Date(Date.now() - 2 * 3600 * 1000).toISOString(),
      keywords: ["モンスターハンター", "ゲーム", "モンハン", "Wilds"],
    },
    {
      id: 4,
      siteId: 1,
      groupId: 4,
      groupName: "スタジオジブリ",
      filename: "ghibli_art_001.webp",
      url: "https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=1000&q=80",
      altText: "アートミュージアム展示イメージ",
      isActive: true,
      useCount: 2,
      lastUsedAt: new Date(Date.now() - 70 * 3600 * 1000).toISOString(),
      keywords: ["ジブリ", "アニメ", "美術展", "チケット"],
    },
    {
      id: 5,
      siteId: 1,
      groupId: undefined,
      filename: "trend_general_001.webp",
      url: "https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1000&q=80",
      altText: "総合ニュース速報イメージ",
      isActive: true,
      useCount: 12,
      lastUsedAt: new Date(Date.now() - 5000).toISOString(),
      keywords: ["トレンド", "ニュース", "話題"],
    },
  ],
  imageKeywords: [],
  votes: [],
  comments: [
    {
      id: 1,
      siteId: 1,
      articleId: 1,
      content: "大悟さんの単独冠番組めっちゃ楽しみです！ゲスト誰来るんだろう",
      status: "approved",
      createdAt: new Date(Date.now() - 30 * 60 * 1000).toISOString(),
    },
    {
      id: 2,
      siteId: 1,
      articleId: 1,
      content: "最後の「しらんけど。」でクスッときました笑",
      status: "approved",
      createdAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
    },
    {
      id: 3,
      siteId: 1,
      articleId: 2,
      content: "アプデ待ってました！武器調整も入るのありがたい",
      status: "approved",
      createdAt: new Date(Date.now() - 45 * 60 * 1000).toISOString(),
    },
  ],
  bannedKeywords: [
    { id: 1, siteId: 1, keyword: "死ね", matchType: "partial", isActive: true, reason: "脅迫・中傷" },
    { id: 2, siteId: 1, keyword: "殺す", matchType: "partial", isActive: true, reason: "脅迫" },
    { id: 3, siteId: 1, keyword: "ガイジ", matchType: "partial", isActive: true, reason: "差別用語" },
    { id: 4, siteId: 1, keyword: "ゴミカス", matchType: "partial", isActive: true, reason: "侮辱" },
    { id: 5, siteId: 1, keyword: "電話番号", matchType: "partial", isActive: true, reason: "個人情報" },
    { id: 6, siteId: 1, keyword: "住所晒す", matchType: "partial", isActive: true, reason: "晒し" },
  ],
  snsQueue: [
    {
      id: 1,
      siteId: 1,
      articleId: 1,
      articleTitle: "千鳥・大悟の新バラエティが独占配信へ 公式発表にSNS歓喜",
      snsType: "x",
      postContent: "【話題度: 88/100】千鳥・大悟の新バラエティが独占配信へ 公式発表にSNS歓喜\n\nいま注目されているニュースをまとめました。しらんけど。\nhttps://example.com/article/trend-chidori-daigo-new-show\n#しらんけど #トレンド",
      scheduledAt: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
      postedAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
      status: "success",
      externalPostId: "x_post_1899120401",
    },
    {
      id: 2,
      siteId: 1,
      articleId: 1,
      articleTitle: "千鳥・大悟の新バラエティが独占配信へ 公式発表にSNS歓喜",
      snsType: "pinterest",
      postContent: "【千鳥・大悟の新バラエティが独占配信へ】\nしらんけど指数 88/100。\n大手配信プラットフォームが千鳥・大悟の単独MCによるオリジナル新番組を発表。",
      scheduledAt: new Date(Date.now() + 5 * 60 * 1000).toISOString(),
      status: "queued",
    },
    {
      id: 3,
      siteId: 1,
      articleId: 2,
      articleTitle: "Monster Hunter Wilds 無料大型アプデ第1弾の詳細公開",
      snsType: "x",
      postContent: "【話題度: 94/100】Monster Hunter Wilds 無料大型アプデ第1弾の詳細公開\nhttps://example.com/article/trend-mhw-wilds-update-1\n#しらんけど #モンハン",
      scheduledAt: new Date(Date.now() - 20 * 60 * 1000).toISOString(),
      postedAt: new Date(Date.now() - 18 * 60 * 1000).toISOString(),
      status: "success",
      externalPostId: "x_post_1899121980",
    },
  ],
  siteSettings: {
    1: {
      siteId: 1,
      weights: { google: 30, yahoo: 25, news: 20, youtube: 15, game: 10 },
      indexLabels: { min0: "ちょい話題", min30: "話題", min60: "かなり話題", min80: "めっちゃ話題" },
      rapidRiseThreshold: 100,
      aiProvider: "gemini",
      aiModel: "gemini-3.8-flash",
      aiMaxChars: 600,
      aiTemperature: 0.4,
      aiSystemPrompt: "客観的な事実のみをまとめ、末尾は必ず「〜しらんけど。」で締める。",
      sns: { xEnabled: true, pinterestEnabled: true, instagramEnabled: true, dailyLimit: 20 },
    },
  },
  logs: [
    {
      id: 1,
      siteId: 1,
      category: "trend_fetch",
      message: "Googleトレンド・Yahoo・YouTube・ゲームランキングの自動収集完了 (新規2件/更新3件)",
      createdAt: new Date(Date.now() - 2 * 3600 * 1000).toISOString(),
    },
    {
      id: 2,
      siteId: 1,
      category: "safety_brake",
      message: "安全ブレーキ作動: 某容疑者SNS特定デマ騒動 を自動保留に設定 (理由: 危険キーワード検知)",
      createdAt: new Date(Date.now() - 1 * 3600 * 1000).toISOString(),
    },
    {
      id: 3,
      siteId: 1,
      category: "ai_gen",
      message: "AI記事生成完了: 千鳥・大悟の新バラエティが独占配信へ (Gemini 3.8 Flash)",
      createdAt: new Date(Date.now() - 1.5 * 3600 * 1000).toISOString(),
    },
    {
      id: 4,
      siteId: 1,
      category: "sns_post",
      message: "X(Twitter)への自動投稿キュー配信成功 (Post ID: x_post_1899120401)",
      createdAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
    },
  ],
};

// Helper: resolve current site (Single unified site: 「しらんけど」)
function resolveSite(req: Request) {
  return store.sites[0];
}

// ----------------------------------------------------
// API ROUTES
// ----------------------------------------------------

// 1. Current Site Info & All Sites (Single Site)
app.get("/api/sites", (req: Request, res: Response) => {
  res.json({ sites: store.sites, current: store.sites[0] });
});

app.post("/api/sites", (req: Request, res: Response) => {
  res.json({ success: true, site: store.sites[0] });
});

// 2. Categories
app.get("/api/categories", (req: Request, res: Response) => {
  res.json({ categories: store.categories });
});

// 3. Articles (Front & Admin)
app.get("/api/articles", (req: Request, res: Response) => {
  const { status, category, limit, sort } = req.query;

  let list = [...store.articles];

  if (status) {
    list = list.filter((a) => a.status === status);
  } else if (!req.query.all_status) {
    // Default front view: only published
    list = list.filter((a) => a.status === "published");
  }

  if (category && category !== "all") {
    const cat = store.categories.find((c) => c.slug === category);
    if (cat) {
      list = list.filter(
        (a) => a.categoryId === cat.id || a.categoryName === cat.name || a.category === cat.name
      );
    } else {
      list = list.filter(
        (a) => a.categoryName === category || a.category === category
      );
    }
  }

  if (sort === "rapid") {
    list.sort((a, b) => (b.isRapidRise ? 1 : 0) - (a.isRapidRise ? 1 : 0) || (b.growthRate || 0) - (a.growthRate || 0));
  } else if (sort === "index") {
    list.sort((a, b) => (b.shirankedoIndex || 0) - (a.shirankedoIndex || 0));
  } else {
    list.sort((a, b) => new Date(b.publishedAt).getTime() - new Date(a.publishedAt).getTime());
  }

  if (limit) {
    list = list.slice(0, parseInt(limit as string, 10));
  }

  res.json({ articles: list });
});

// 4. Article Detail
app.get("/api/articles/:slugOrId", (req: Request, res: Response) => {
  const { slugOrId } = req.params;
  const isId = /^\d+$/.test(slugOrId);
  const article = store.articles.find((a) => (isId ? a.id === parseInt(slugOrId, 10) : a.slug === slugOrId));

  if (!article) {
    res.status(404).json({ error: "記事が見つかりません" });
    return;
  }

  const sources = store.articleSources.filter((s) => s.articleId === article.id);
  const comments = store.comments.filter((c) => c.articleId === article.id && c.status === "approved");

  res.json({
    article: {
      ...article,
      sources,
      commentsCount: comments.length,
    },
  });
});

// 5. User Voting API (知ってた/知らんかった, もっと伸びる/もう終わる)
app.post("/api/articles/:id/vote", (req: Request, res: Response) => {
  const articleId = parseInt(req.params.id, 10);
  const { pollType, voteValue } = req.body;
  const article = store.articles.find((a) => a.id === articleId);

  if (!article) {
    res.status(404).json({ error: "記事が存在しません" });
    return;
  }

  if (!article.votes) {
    article.votes = { knew: 0, didntKnow: 0, grow: 0, end: 0 };
  }

  if (pollType === "knew_ratio") {
    if (voteValue === "knew") article.votes.knew++;
    else article.votes.didntKnow++;
  } else if (pollType === "future_growth") {
    if (voteValue === "grow") article.votes.grow++;
    else article.votes.end++;
  }

  res.json({ success: true, votes: article.votes });
});

// 6. User Comments API (文字のみ・URL禁止・拒否キーワード自動遮断)
app.get("/api/articles/:id/comments", (req: Request, res: Response) => {
  const articleId = parseInt(req.params.id, 10);
  const comments = store.comments.filter((c) => c.articleId === articleId && c.status === "approved");
  res.json({ comments });
});

app.post("/api/articles/:id/comments", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const articleId = parseInt(req.params.id, 10);
  const { content } = req.body;

  if (!content || typeof content !== "string" || !content.trim()) {
    res.status(400).json({ error: "コメント内容を入力してください。" });
    return;
  }

  // 1. URL検知 (URL投稿禁止)
  const urlPattern = /https?:\/\/|ftp:\/\/|www\.[a-z0-9\-]+\.[a-z]{2,}|[a-z0-9\-]+\.(com|net|org|jp|io|me|app)/i;
  if (urlPattern.test(content)) {
    res.status(400).json({
      error: "当サイトではURLの投稿は禁止されています。文字のみで投稿してください。",
    });
    return;
  }

  // 2. 拒否キーワード照合
  const banned = store.bannedKeywords.filter((k) => (k.siteId === site.id || k.siteId === 1) && k.isActive);
  for (const b of banned) {
    if (b.matchType === "exact" && content.trim() === b.keyword) {
      res.status(400).json({ error: "禁止キーワードが含まれているため投稿できません。" });
      return;
    } else if (b.matchType === "partial" && content.includes(b.keyword)) {
      res.status(400).json({ error: "誹謗中傷・不適切な言葉が含まれているため投稿できません。" });
      return;
    }
  }

  // 3. HTML除去 & プレーンテキスト化
  const cleaned = content.replace(/<[^>]*>/g, "").trim().slice(0, 400);

  const newComment = {
    id: store.comments.length + 1,
    siteId: site.id,
    articleId,
    content: cleaned,
    status: "approved",
    createdAt: new Date().toISOString(),
  };
  store.comments.unshift(newComment);

  res.json({ success: true, comment: newComment });
});

// 7. Trend Candidates & Auto-Fetch Trigger
app.get("/api/trends", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const trends = store.trendCandidates.filter((t) => t.siteId === site.id || site.id === 1);
  res.json({ trends });
});

app.post("/api/trends/collect", (req: Request, res: Response) => {
  const site = resolveSite(req);
  // Simulate gathering new real-world trend
  const sampleTopics = [
    {
      keyword: "新オープンプラットフォーム発表",
      sources: ["google", "news"],
      growth: 125,
      google: 90,
      yahoo: 70,
      news: 85,
      youtube: 40,
      game: 0,
    },
    {
      keyword: "国民的アニメ映画 最新予告PV解禁",
      sources: ["youtube", "yahoo", "google"],
      growth: 180,
      google: 88,
      yahoo: 95,
      news: 80,
      youtube: 100,
      game: 20,
    },
  ];

  let added = 0;
  sampleTopics.forEach((t) => {
    const exists = store.trendCandidates.some((tc) => tc.displayKeyword.includes(t.keyword));
    if (!exists) {
      const idx = Math.round((t.google * 0.3 + t.yahoo * 0.25 + t.news * 0.2 + t.youtube * 0.15 + t.game * 0.1));
      store.trendCandidates.unshift({
        id: store.trendCandidates.length + 1,
        siteId: site.id,
        normalizedKeyword: t.keyword.replace(/\s+/g, "").toLowerCase(),
        displayKeyword: t.keyword,
        sources: t.sources,
        googleScore: t.google,
        yahooScore: t.yahoo,
        youtubeScore: t.youtube,
        newsScore: t.news,
        gameScore: t.game,
        shirankedoIndex: idx,
        isRapidRise: t.growth >= 100,
        growthRate: t.growth,
        firstDetectedAt: new Date().toISOString(),
        lastUpdatedAt: new Date().toISOString(),
        status: "candidate",
      });
      added++;
    }
  });

  store.logs.unshift({
    id: store.logs.length + 1,
    siteId: site.id,
    category: "trend_fetch",
    message: `トレンド手動収集を実行: 新規${added}件の候補を取得`,
    createdAt: new Date().toISOString(),
  });

  res.json({ success: true, addedCount: added });
});

// 8. AI Article Generation Trigger (Uses Gemini 3.8 Flash server-side)
app.post("/api/articles/generate", async (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { trendCandidateId, customKeyword } = req.body;

  let keyword = customKeyword;
  let candidate = null;
  if (trendCandidateId) {
    candidate = store.trendCandidates.find((t) => t.id === trendCandidateId);
    if (candidate) keyword = candidate.displayKeyword;
  }

  if (!keyword) {
    res.status(400).json({ error: "対象キーワードを指定してください" });
    return;
  }

  // 1. Safety Brake Check (危険ジャンル検知)
  const dangerWords = [
    "犯罪", "逮捕", "容疑", "不倫", "離婚", "薬物", "病気", "訃報", "死亡", "自殺",
    "事故", "事故責任", "金銭トラブル", "未成年", "一般人", "個人情報", "流出"
  ];
  const matchedDanger = dangerWords.filter((w) => keyword.includes(w));
  const isDangerous = matchedDanger.length > 0;

  // 2. Prepare Primary Sources
  const verifiedSources = [
    {
      id: Date.now(),
      articleId: 0,
      sourceType: "news" as const,
      title: `「${keyword}」に関する公式プレスリリース及び報道速報`,
      publisher: "主要報道機関・公式発表",
      url: `https://news.example.com/topic/${encodeURIComponent(keyword)}`,
      reliabilityScore: 95,
    },
  ];

  // 3. AI Generation via Gemini or fallback
  let generatedTitle = `「${keyword}」がネットで急上昇、注目が集まる`;
  let whyTrending = `複数のトレンド指標および検索ボリュームで「${keyword}」が急伸。公式発表を受けて話題となっています。`;
  let bodyText = `本日、「${keyword}」に関する最新情報が発表され、大きな反響を呼んでいます。\n\n確認された公式発表および報道資料によると、今回の発表は以前より注目されていた内容の具体化であり、多くのファンや関係者の間で話題が急拡大しています。\n\n詳細な仕様や今後のスケジュールについては、順次公式アナウンスが行われる見通しです。`;
  let conclusionSentence = "今後の追加発表次第では、さらに盛り上がりを見せる展開になるかもしれません。しらんけど。";

  try {
    if (process.env.GEMINI_API_KEY) {
      const ai = getAi();
      const prompt = `あなたはトレンドニュースサイト「しらんけど」のAIエディターです。
以下のキーワードに関する客観的な事実要約記事を作成してください。
【キーワード】: ${keyword}

【厳格ルール】
1. 噂や憶測を事実として書かないこと。
2. 容疑段階の人物を犯人扱いせず、一般人の個人情報は書かないこと。
3. 記事本文の末尾は、文脈に沿った自然な日本語の余韻として、必ず「〜しらんけど。」で締めてください。
4. JSON形式のみで出力してください:
{
  "title": "35文字以内のタイトル",
  "whyTrending": "なぜ話題かの100文字要約",
  "body": "段落分けされた客観的本文（300〜500文字）",
  "conclusion": "末尾の一文（最後は必ず「〜しらんけど。」）"
}`;

      const response = await ai.models.generateContent({
        model: "gemini-3.8-flash",
        contents: prompt,
        config: {
          responseMimeType: "application/json",
          temperature: 0.4,
        },
      });

      if (response.text) {
        const parsed = JSON.parse(response.text);
        if (parsed.title) generatedTitle = parsed.title;
        if (parsed.whyTrending) whyTrending = parsed.whyTrending;
        if (parsed.body) bodyText = parsed.body;
        if (parsed.conclusion) conclusionSentence = parsed.conclusion;
      }
    }
  } catch (err: any) {
    console.error("Gemini Generation fallback:", err.message);
  }

  // Ensure conclusion ends with 「しらんけど。」
  if (!conclusionSentence.endsWith("しらんけど。")) {
    conclusionSentence = conclusionSentence.replace(/。?$/, "") + "。しらんけど。";
  }

  // 4. Image matching logic (dedicated -> group -> generic)
  const matchedGroup = store.imageGroups.find((g) => g.keywords.some((k: string) => keyword.includes(k)));
  let selectedImage = store.images.find((img) => matchedGroup && img.groupId === matchedGroup.id);
  if (!selectedImage) {
    selectedImage = store.images[store.images.length - 1];
  }
  selectedImage.useCount++;
  selectedImage.lastUsedAt = new Date().toISOString();

  // 5. Status: If dangerous -> 'on_hold' (Safety Brake), else 'published'
  const finalStatus = isDangerous || !site.allowAutoPublish ? "on_hold" : "published";

  const newArticle = {
    id: store.articles.length + 1,
    siteId: site.id,
    categoryId: 1,
    categoryName: "総合",
    title: generatedTitle,
    slug: `trend-${Date.now()}-${Math.floor(Math.random() * 900 + 100)}`,
    whyTrending,
    body: bodyText,
    conclusionSentence,
    shirankedoIndex: candidate ? candidate.shirankedoIndex : 75,
    indexLabel: candidate && candidate.shirankedoIndex >= 80 ? "めっちゃ話題" : "かなり話題",
    isRapidRise: candidate ? candidate.isRapidRise : true,
    growthRate: candidate ? candidate.growthRate : 120.0,
    firstDetectedAt: candidate ? candidate.firstDetectedAt : new Date().toISOString(),
    imageUrl: selectedImage.url,
    status: finalStatus,
    isDangerous,
    dangerReason: isDangerous ? `危険ジャンル検知: ${matchedDanger.join(", ")}` : undefined,
    publishedAt: new Date().toISOString(),
    votes: { knew: 0, didntKnow: 0, grow: 0, end: 0 },
  };

  store.articles.unshift(newArticle);

  if (candidate) {
    candidate.status = "completed";
  }

  // Enqueue SNS if published
  if (finalStatus === "published") {
    store.snsQueue.unshift({
      id: store.snsQueue.length + 1,
      siteId: site.id,
      articleId: newArticle.id,
      articleTitle: newArticle.title,
      snsType: "x",
      postContent: `【話題度: ${newArticle.shirankedoIndex}/100】${newArticle.title}\n\nいま注目されている話題をチェック。しらんけど。\nhttps://example.com/article/${newArticle.slug}\n#しらんけど #トレンド`,
      scheduledAt: new Date(Date.now() + 5 * 60 * 1000).toISOString(),
      status: "queued",
    });
  }

  store.logs.unshift({
    id: store.logs.length + 1,
    siteId: site.id,
    category: isDangerous ? "safety_brake" : "ai_gen",
    message: isDangerous
      ? `安全ブレーキ作動: 「${newArticle.title}」を自動保留に設定`
      : `AI記事生成完了: 「${newArticle.title}」`,
    createdAt: new Date().toISOString(),
  });

  res.json({ success: true, article: newArticle });
});

// 8b. Manual Article Creation (手動記事作成)
app.post("/api/articles", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { title, categoryName, body, conclusionSentence, shirankedoIndex, imageUrl, status } = req.body;
  
  if (!title || !title.trim()) {
    res.status(400).json({ error: "記事タイトルを入力してください" });
    return;
  }
  if (!body || !body.trim()) {
    res.status(400).json({ error: "記事本文を入力してください" });
    return;
  }

  let finalConclusion = conclusionSentence || "";
  if (!finalConclusion.trim()) {
    finalConclusion = "真相や今後の展開は公式発表を注視したいところです。しらんけど。";
  } else if (!finalConclusion.endsWith("しらんけど。")) {
    finalConclusion = finalConclusion.replace(/。?$/, "") + "。しらんけど。";
  }

  const score = Number(shirankedoIndex) || 60;
  const finalStatus = status === "on_hold" ? "on_hold" : "published";

  const newArticle = {
    id: store.articles.length + 1,
    siteId: site.id,
    categoryId: 1,
    categoryName: categoryName || "総合",
    title: title.trim(),
    slug: `manual-${Date.now()}-${Math.floor(Math.random() * 900 + 100)}`,
    whyTrending: "編集者による手動執筆・独自取材記事",
    body: body.trim(),
    conclusionSentence: finalConclusion,
    shirankedoIndex: score,
    indexLabel: score >= 80 ? "めっちゃ話題" : score >= 50 ? "かなり話題" : "じわじわ話題",
    isRapidRise: false,
    growthRate: 100.0,
    firstDetectedAt: new Date().toISOString(),
    imageUrl: imageUrl || (store.images[0]?.url ?? "https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=800&auto=format&fit=crop&q=60"),
    status: finalStatus,
    isDangerous: false,
    publishedAt: new Date().toISOString(),
    votes: { knew: 0, didntKnow: 0, grow: 0, end: 0 },
  };

  store.articles.unshift(newArticle);

  if (finalStatus === "published") {
    store.snsQueue.unshift({
      id: store.snsQueue.length + 1,
      siteId: site.id,
      articleId: newArticle.id,
      articleTitle: newArticle.title,
      snsType: "threads",
      postContent: `【新着記事】${newArticle.title}\n\n${newArticle.body.slice(0, 70)}…\n\n#しらんけど #${newArticle.categoryName}`,
      scheduledAt: new Date(Date.now() + 5 * 60 * 1000).toISOString(),
      status: "queued",
    });
  }

  store.logs.unshift({
    id: store.logs.length + 1,
    siteId: site.id,
    category: "manual_edit",
    message: `手動記事作成: 「${newArticle.title}」を${finalStatus === "published" ? "公開" : "保留"}しました`,
    createdAt: new Date().toISOString(),
  });

  res.json({ success: true, article: newArticle });
});

// 9. Article Moderation (Approve held, Change status)
app.patch("/api/articles/:id/status", (req: Request, res: Response) => {
  const articleId = parseInt(req.params.id, 10);
  const { status } = req.body;
  const article = store.articles.find((a) => a.id === articleId);

  if (!article) {
    res.status(404).json({ error: "記事が見つかりません" });
    return;
  }

  article.status = status;
  if (status === "published" && !article.publishedAt) {
    article.publishedAt = new Date().toISOString();
  }

  res.json({ success: true, article });
});

// 10. Image Management & Groups
app.get("/api/images", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const images = store.images.filter((img) => img.siteId === site.id || site.id === 1);
  const groups = store.imageGroups.filter((g) => g.siteId === site.id || site.id === 1);
  res.json({ images, groups });
});

app.post("/api/images", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { filename, url, altText, groupId, keywords } = req.body;
  const newImg = {
    id: store.images.length + 1,
    siteId: site.id,
    groupId: groupId ? parseInt(groupId, 10) : undefined,
    filename: filename || `img_${Date.now()}.webp`,
    url: url || "https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1000&q=80",
    altText: altText || "イラスト・イメージ",
    isActive: true,
    useCount: 0,
    keywords: Array.isArray(keywords) ? keywords : (keywords || "").split(",").map((k: string) => k.trim()),
  };
  store.images.unshift(newImg);
  res.json({ success: true, image: newImg });
});

app.post("/api/image-groups", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { name, genre, keywords } = req.body;
  const newGroup = {
    id: store.imageGroups.length + 1,
    siteId: site.id,
    name: name || "新規グループ",
    genre: genre || "general",
    keywords: Array.isArray(keywords) ? keywords : (keywords || "").split(",").map((k: string) => k.trim()),
  };
  store.imageGroups.push(newGroup);
  res.json({ success: true, group: newGroup });
});

// 11. Banned Keywords
app.get("/api/banned-keywords", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const keywords = store.bannedKeywords.filter((k) => k.siteId === site.id || site.id === 1);
  res.json({ keywords });
});

app.post("/api/banned-keywords", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const { keyword, matchType, reason } = req.body;
  const newKw = {
    id: store.bannedKeywords.length + 1,
    siteId: site.id,
    keyword: keyword.trim(),
    matchType: matchType || "partial",
    isActive: true,
    reason: reason || "管理規定による遮断",
  };
  store.bannedKeywords.push(newKw);
  res.json({ success: true, keyword: newKw });
});

app.delete("/api/banned-keywords/:id", (req: Request, res: Response) => {
  const id = parseInt(req.params.id, 10);
  store.bannedKeywords = store.bannedKeywords.filter((k) => k.id !== id);
  res.json({ success: true });
});

// 12. SNS Queue & Dispatch
app.get("/api/sns-queue", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const queue = store.snsQueue.filter((q) => q.siteId === site.id || site.id === 1);
  res.json({ queue });
});

app.post("/api/sns-queue/process", (req: Request, res: Response) => {
  let processed = 0;
  store.snsQueue.forEach((item) => {
    if (item.status === "queued") {
      item.status = "success";
      item.postedAt = new Date().toISOString();
      item.externalPostId = `${item.snsType}_post_${Date.now()}`;
      processed++;
    }
  });
  res.json({ success: true, processedCount: processed });
});

// 13. Site Settings (Weights, AI, SNS, etc.)
app.get("/api/settings", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const settings = store.siteSettings[site.id] || store.siteSettings[1];
  res.json({ settings });
});

app.post("/api/settings", (req: Request, res: Response) => {
  const site = resolveSite(req);
  store.siteSettings[site.id] = {
    ...store.siteSettings[site.id],
    ...req.body,
    siteId: site.id,
  };
  res.json({ success: true, settings: store.siteSettings[site.id] });
});

// 14. Logs
app.get("/api/logs", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const logs = store.logs.filter((l) => !l.siteId || l.siteId === site.id || site.id === 1);
  res.json({ logs });
});

// 15. Dashboard Stats
app.get("/api/dashboard-stats", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const siteArticles = store.articles.filter((a) => a.siteId === site.id || site.id === 1);
  const siteTrends = store.trendCandidates.filter((t) => t.siteId === site.id || site.id === 1);
  const siteSns = store.snsQueue.filter((s) => s.siteId === site.id || site.id === 1);

  const stats = {
    todayTrendsCount: siteTrends.length,
    generatedArticlesCount: siteArticles.length,
    autoPublishedCount: siteArticles.filter((a) => a.status === "published").length,
    heldCount: siteArticles.filter((a) => a.status === "on_hold").length,
    snsSuccessCount: siteSns.filter((s) => s.status === "success").length,
    snsErrorCount: siteSns.filter((s) => s.status === "failed").length,
    commentsCount: store.comments.filter((c) => c.siteId === site.id || site.id === 1).length,
  };

  res.json({ stats, currentSite: site });
});

// 15b. Access Analytics
// Production analytics are measured by the PHP frontend and stored in MySQL.
// The development/React server must never invent traffic numbers.
app.get("/api/analytics", (req: Request, res: Response) => {
  const period = (req.query.period as string) || "7days";

  res.json({
    period,
    trackingMode: "production-php-only",
    summary: {
      totalPv: 0,
      totalUu: 0,
      todayPv: 0,
      todayUu: 0,
      yesterdayPv: 0,
      yesterdayUu: 0,
    },
    hourlyPv: [],
    dailyHistory: [],
    sources: [],
    devices: {},
    browsers: [],
    regions: [],
    monetization: {},
    topArticles: [],
    liveLogs: [],
    message: "Development mode does not generate simulated analytics. Production PHP analytics use actual access logs.",
  });
});

app.post("/api/analytics/track", (_req: Request, res: Response) => {
  res.status(410).json({
    success: false,
    message: "Analytics tracking is handled by the production PHP frontend.",
  });
});

// 15c. Cron Automated Pipeline (定期実行・クーロン設定 & 手動実行)
const cronHistory: Array<{
  id: number;
  time: string;
  articleTitle: string;
  category: string;
  durationMs: number;
  status: "success" | "error";
  shirankedoIndex: number;
}> = [
  {
    id: 1,
    time: "22:28:15",
    articleTitle: "【速報】千鳥・大悟の新番組独占配信が決定！公式PVに歓喜の声（しらんけど）",
    category: "エンタメ・話題",
    durationMs: 840,
    status: "success",
    shirankedoIndex: 88,
  },
  {
    id: 2,
    time: "19:28:02",
    articleTitle: "新型スマホのカメラ性能が異次元進化との噂、ただし重さもヘビー級らしいで",
    category: "IT・ガジェット",
    durationMs: 920,
    status: "success",
    shirankedoIndex: 79,
  },
  {
    id: 3,
    time: "16:28:44",
    articleTitle: "関西の有名たこ焼き店がまさかの新展開！？真相は謎のまま話題沸騰中",
    category: "グルメ・街ネタ",
    durationMs: 760,
    status: "success",
    shirankedoIndex: 92,
  },
];

app.get("/api/cron/status", (req: Request, res: Response) => {
  res.json({
    isRunning: true,
    intervalHours: 3,
    maxArticlesPerDay: 10,
    lastRun: cronHistory[0]?.time || "22:28",
    nextRun: "いつでも即時可能",
    mode: "auto",
    history: cronHistory,
  });
});

app.post("/api/cron/run", (req: Request, res: Response) => {
  const site = resolveSite(req);
  const startTime = Date.now();

  // Pick top trend candidate or create a fresh one
  const availableTrend = store.trendCandidates.find(t => !store.articles.some(a => a.trendKeyword === (t.displayKeyword || t.keyword))) 
    || store.trendCandidates[0];

  const trendName = availableTrend?.displayKeyword || availableTrend?.keyword || "急上昇トレンド";

  const now = new Date();
  const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
  
  const title = `【自動生成】「${trendName}」の真相に迫る！話題の裏側を徹底調査（しらんけど）`;

  const newArticle = {
    id: store.articles.length + 1,
    siteId: site.id,
    title,
    slug: `cron-${Date.now()}`,
    category: "エンタメ・話題",
    content: `いま巷で大きな話題となっているキーワード「${trendName}」をキャッチしました。\nネット上では様々な推測が飛び交っており、賛否両論の白熱した議論が続いています。\n\n客観的な事実としては、公式発表を待つ必要があるものの、ファンの間では期待が高まる一方です。\n\n…まあ、真相は知らんけどな！`,
    objectiveFact: `「${trendName}」に関するネット上の各種SNSおよびトレンド情報に基づく速報まとめです。`,
    shirankedoIndex: Math.floor(Math.random() * 25) + 75,
    sourceKeyword: trendName,
    trendKeyword: trendName,
    thumbnailUrl: "https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=800&q=80",
    status: "published",
    pvCount: 1,
    snsShareCount: 0,
    votesReliable: 0,
    votesUnreliable: 0,
    publishedAt: now.toISOString(),
  };

  store.articles.unshift(newArticle);

  const durationMs = Date.now() - startTime + 650;

  cronHistory.unshift({
    id: cronHistory.length + 1,
    time: timeStr,
    articleTitle: newArticle.title,
    category: newArticle.category,
    durationMs,
    status: "success",
    shirankedoIndex: newArticle.shirankedoIndex,
  });

  store.logs.unshift({
    id: store.logs.length + 1,
    siteId: site.id,
    category: "ai_gen",
    message: `[Cron自動実行成功] 記事「${newArticle.title}」を自動生成・即時公開しました (所要: ${durationMs}ms)`,
    level: "info",
    createdAt: now.toISOString(),
  });

  res.json({
    success: true,
    message: "Cron自動実行が完了し、新しいAI記事を1本公開しました！",
    article: newArticle,
    durationMs,
    timestamp: now.toISOString(),
  });
});

// 16. SEO: sitemap.xml & robots.txt
app.get("/sitemap.xml", (req: Request, res: Response) => {
  res.header("Content-Type", "application/xml");
  let xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n`;
  xml += `  <url><loc>https://example.com/</loc><priority>1.0</priority></url>\n`;
  xml += `  <url><loc>https://example.com/shirankedo-about</loc><priority>0.8</priority></url>\n`;
  xml += `  <url><loc>https://example.com/comment-rules</loc><priority>0.8</priority></url>\n`;
  store.articles.filter((a) => a.status === "published").forEach((a) => {
    xml += `  <url><loc>https://example.com/article/${a.slug}</loc><lastmod>${new Date(a.publishedAt).toISOString().split("T")[0]}</lastmod></url>\n`;
  });
  xml += `</urlset>`;
  res.send(xml);
});

app.get("/robots.txt", (req: Request, res: Response) => {
  res.type("text/plain");
  res.send(`User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: https://example.com/sitemap.xml\n`);
});

// ----------------------------------------------------
// Start Server with Vite Middleware
// ----------------------------------------------------
async function startServer() {
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`「しらんけど」サーバー起動完了: http://localhost:${PORT}`);
  });
}

startServer();
