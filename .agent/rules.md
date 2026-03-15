# 🏆 Master Agent Rules: Finance App (Elite Standard)

## 1. Strategické Plánovanie (Plan-First)

- **Implementation Plan:** Pre každú úlohu nad 3 kroky povinne vytvor a predlož `implementation_plan.md`.
- **Stop & Re-plan:** Ak narazíš na neočakávanú prekážku, zastav. Nepokúšaj sa o "hacky". Navrhni revidovaný plán.
- **Task Management:** Priebežne aktualizuj `.agent/task.md`. Používaj stavy:
    - `[ ]` (čaká)
    - `[/]` (v procese)
    - `[x]` (hotovo)

## 2. Technický Štandard (Laravel 12 & PHP 8.2+)

- **Strict Typing:** Povinné typovanie pre všetky properties, argumenty a návratové hodnoty. Žiadne "mixed", ak to nie je nevyhnutné.
- **Filament PHP (v3):** Prioritne používaj schémami riadený vývoj (Resources, Widgets). Komplexnú biznis logiku vyčleň do **Actions** alebo **Services**.
- **Clean Code:** Dodržuj PSR-12 a princípy SOLID. Kód musí byť sémantický a DRY (Don't Repeat Yourself).
- **Money Handling:** Pre finančné hodnoty nikdy nepoužívaj `float`. Použi `decimal` alebo ukladaj hodnoty v centoch ako `integer`.

## 3. UI/UX & Tailwind 4

- **Modern Syntax:** Využívaj utility-first prístup Tailwind 4 bez zbytočných legacy konfigurácií.
- **Luxusný Vizuál:** Implementuj **Glassmorphism**, jemné gradienty a prácu s hĺbkou. Aplikácia musí pôsobiť prémiovo a dôveryhodne.
- **Mobile-First:** Responzivita je základ. Každý komponent musí byť plne funkčný a estetický na mobilných zariadeniach.

## 4. Inteligentná Autonómia (Zero-Hand-Holding)

- **Proaktívny Debugging:** Ak build (Vite) alebo server (Artisan) vyhodí chybu, oprav ju autonómne. Analyzuj logy v `storage/logs` bez pýtania.
- **Subagent Strategy:** Pre hĺbkový výskum nových knižníc alebo paralelnú analýzu riešení deleguj prácu na subagentov.
- **Verification:** Pred odovzdaním spusti `php artisan test` a manuálne over funkčnosť v prehliadači. Nikdy neoznač úlohu za hotovú bez dôkazu.

## 5. Slučka Neustáleho Zlepšovania (Self-Improvement)

- **Lessons Log:** Každú opravu po spätnej väzbe od používateľa zapíš do `.agent/lessons.md`.
- **Staff Engineer Approval:** Pred finalizáciou sa pýtaj: _"Je toto riešenie škálovateľné, bezpečné a hodné senior architekta?"_
- **Refactoring:** Ak narazíš na starý alebo neefektívny kód pri práci na inej úlohe, navrhni refaktoring.

## 6. Jadrové Princípy (Core Principles)

- **Simplicity First:** Rob zmeny čo najjednoduchšie. Zasiahni len kód, ktorý je nevyhnutný.
- **No Laziness:** Hľadaj koreňové príčiny problémov (root cause), nie symptómy. Žiadne dočasné fixy.
- **Security First:** Pri každej zmene v DB alebo API zváž bezpečnosť (SQL injection, Mass Assignment, Permissions, šifrovanie citlivých dát).
