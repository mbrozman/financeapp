# Lessons Learned: Finance App

## Redizajn Dashboardu (Marec 2026)
- **Problem:** Inline CSS a hardcoded štýly v Blade sú neudržateľné a náchylné na prebíjanie Filamentom. Triedy Tailwindu (JIT) nefungujú bez buildu.
- **Solution:** Vždy voliť cestu cez **Filament Theme** a `tailwind.config.js` extension. Umožňuje to čistý Blade kód a globálnu správu rádiusov/farieb.
- **Money Handling:** Práca s `float` pri finančných widgetoch vedie k nepresnostiam. Vždy používať **`BigDecimal`** a v View vrstve pracovať s centami (**integers**).
- **Strict Typing:** Dôsledné typovanie v PHP triedach widgetov zvyšuje stabilitu pri komplexných transformáciách dát.
