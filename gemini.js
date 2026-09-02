const { GoogleGenAI } = require("@google/genai");

async function run() {
    if (!process.env.GEMINI_API_KEY) {
        throw new Error("[GeminiConfig][API_KEY_MISSING] GEMINI_API_KEY ausente");
    }

    const prompt = process.argv.slice(2).join(" ").trim() || "Explique resumidamente a diferença entre mapas e rotas.";

    console.log("[GeminiRequest][START]");

    const ai = new GoogleGenAI({
        apiKey: process.env.GEMINI_API_KEY
    });

    const response = await ai.models.generateContent({
        model: "gemini-2.5-flash",
        contents: prompt
    });

    console.log("[GeminiRequest][SUCCESS]");
    console.log(response.text);
}

run().catch(error => {
    console.error("[GeminiRequest][FAILED]", error.message);
    console.error("[GeminiRequest][CAUSE]", error.cause || "Causa não informada");
    process.exitCode = 1;
});
