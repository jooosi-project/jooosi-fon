import { writeFile } from "node:fs/promises";

// Helper to slugify font family names
function slugify(str) {
  return str
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)+/g, "");
}

async function saveFontsData(fonts) {
  await writeFile(
    new URL("../../google-fonts.json", import.meta.url),
    JSON.stringify(fonts),
    "utf8",
  );
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
  return metadataContent.familyMetadataList;
}

// Fetch Google Fonts webfont data
async function fetchWebfont(webfontApiKey) {
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
  const items = {};

  for (const item of webfontContent.items) {
    item.variants = item.variants.map((variant) => {
      if (variant === "regular") return "400";
      if (variant === "italic") return "400i";
      if (/^(\d+)(italic)$/.test(variant)) {
        return variant.replace("italic", "i");
      }
      return variant;
    });
    items[item.family] = item;
  }

  return items;
}

async function main() {
  const webfontApiKey = process.env.GOOGLE_FONTS_API_KEY;
  if (!webfontApiKey) {
    console.error("GOOGLE_FONTS_API_KEY environment variable is not set.");
    return 1;
  }

  try {
    const metadataFonts = await fetchMetadata();
    const webfont = await fetchWebfont(webfontApiKey);

    console.log(`Found ${metadataFonts.length} fonts.`);

    if (metadataFonts.length === 0) {
      console.warn("No fonts found in the metadata.");
      return 0;
    }

    console.log("Processing fonts data...");

    const fonts = [];

    for (const [index, metadataFont] of metadataFonts.entries()) {
      const font = {
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

      for (const variant of Object.keys(metadataFont.fonts)) {
        if (webfont[metadataFont.family]?.variants.includes(variant)) {
          font.variants.push(variant);
        }
      }

      fonts.push(font);

      const processed = index + 1;
      if (processed === metadataFonts.length || processed % 100 === 0) {
        console.log(`Processed ${processed}/${metadataFonts.length} fonts.`);
      }
    }

    console.log("Fonts data processed. Saving...");

    await saveFontsData(fonts);
    console.log("Fonts data saved successfully.");
  } catch (error) {
    if (error instanceof Error) {
      console.error(error.message);
    } else {
      console.error(String(error));
    }
    return 1;
  }

  return 0;
}

process.exitCode = await main();
