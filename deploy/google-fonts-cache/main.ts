import { ProgressBar } from "@std/cli/unstable-progress-bar";

interface MetadataFont {
  family: string;
  displayName?: string;
  category: string;
  dateAdded: string;
  lastModified: string;
  popularity: number;
  designers: string[];
  subsets: string[];
  axes: unknown[];
  fonts: Record<string, {
    url?: string;
    unicodeRange?: string;
    [key: string]: unknown;
  }>;
}

interface WebfontItem {
  family: string;
  variants: string[];
  version: string;
}

interface Font {
  slug: string;
  addedAt: string;
  axes: any[];
  category: string;
  designers: string[];
  displayName?: string;
  family: string;
  modifiedAt: string;
  subsets: string[];
  variants: string[];
  updatedAt: string;
  popularity: number;
  version: string;
  isSupportVariable: boolean;
}

// Helper to slugify font family names
function slugify(str: string): string {
  return str
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)+/g, "");
}

async function saveFontsData(fonts: Font[]): Promise<void> {
  await Deno.writeTextFile("../../google-fonts.json", JSON.stringify(fonts));
}

// Fetch Google Fonts metadata
async function fetchMetadata() {
  console.log("Fetching fonts from Google Fonts API (metadata)...");

  const res = await fetch("https://fonts.google.com/metadata/fonts");
  if (!res.ok) {
    throw new Error(
      "Error while fetching fonts from Google Fonts API (metadata)",
    );
  }

  const metadataContent = await res.json();
  return metadataContent.familyMetadataList as MetadataFont[];
}

// Fetch Google Fonts webfont data
async function fetchWebfont(webfontApiKey: string) {
  console.log("Fetching fonts from Google Fonts API (webfont)...");

  const res = await fetch(
    `https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=${webfontApiKey}`,
  );
  if (!res.ok) {
    throw new Error(
      "Error while fetching fonts from Google Fonts API (webfont)",
    );
  }

  const webfontContent = await res.json();
  const items: Record<string, WebfontItem> = {};

  for (const item of webfontContent.items) {
    item.variants = item.variants.map((v: string) => {
      if (v === "regular") return "400";
      if (v === "italic") return "400i";
      if (/^(\d+)(italic)$/.test(v)) return v.replace("italic", "i");
      return v;
    });
    items[item.family] = item;
  }

  return items;
}

async function main() {
  const webfontApiKey = Deno.env.get("GOOGLE_FONTS_API_KEY");
  if (!webfontApiKey) {
    console.error("GOOGLE_FONTS_API_KEY environment variable is not set.");
    Deno.exit(1);
  }

  try {
    const metadataFonts = await fetchMetadata();
    const webfont = await fetchWebfont(webfontApiKey);

    console.log(
      `Found ${metadataFonts.length} fonts.`,
    );

    if (metadataFonts.length === 0) {
      console.warn("No fonts found in the metadata.");
      Deno.exit(0);
    }

    console.log("Processing fonts data...");

    // Initialize an array to hold the processed font data
    const fonts: Font[] = [];

    const bar = new ProgressBar({
      max: metadataFonts.length,
      fmt(x) {
        return `[${x.styledTime}] [${x.progressBar}] [${x.value}/${x.max} fonts]`;
      },
    });

    for (const metadataFont of metadataFonts) {
      const font: Font = {
        slug: slugify(metadataFont.family),
        addedAt: metadataFont.dateAdded,
        axes: metadataFont.axes,
        category: metadataFont.category,
        designers: metadataFont.designers,
        displayName: metadataFont.displayName,
        family: metadataFont.family,
        modifiedAt: metadataFont.lastModified,
        subsets: metadataFont.subsets,
        variants: [],
        updatedAt: new Date().toISOString(),
        popularity: metadataFont.popularity,
        version: webfont[metadataFont.family]?.version || "unknown",
        isSupportVariable: metadataFont.axes.length > 0,
      };

      for (const k in metadataFont.fonts) {
        if (webfont[metadataFont.family]?.variants.includes(k)) {
          font.variants.push(k);
        }
      }

      fonts.push(font);

      bar.value += 1;
    }

    await bar.stop();

    console.log("Fonts data processed. Saving...");

    await saveFontsData(fonts);
    console.log("Fonts data saved successfully.");
  } catch (error) {
    if (error instanceof Error) {
      console.error(error.message);
    } else {
      console.error(String(error));
    }
    Deno.exit(1);
  }

  Deno.exit(0);
}

if (import.meta.main) {
  main().catch((error) => {
    console.error("Fatal error:", error);
    Deno.exit(1);
  });
}
