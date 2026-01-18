import { defineConfig } from "tsup";

export default defineConfig({
	entry: [
		"src/index.ts",
		"src/config-mapping/index.ts",
		"src/assertions/index.ts",
		"src/fixtures/index.ts",
		"src/paths/index.ts",
	],
	format: ["esm", "cjs"],
	bundle: false,
	dts: {
		compilerOptions: {
			skipLibCheck: true,
			skipDefaultLibCheck: true,
		},
	},
	splitting: false,
	sourcemap: true,
	clean: true,
	shims: false,
	platform: "neutral",
});
