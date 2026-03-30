<?php
echo "1. Odinštalovanie Tailwind 4...\n";
passthru('npm uninstall tailwindcss @tailwindcss/vite @tailwindcss/forms @tailwindcss/typography');

echo "2. Inštalácia Tailwind 3 a závislostí...\n";
passthru('npm install -D tailwindcss@^3.4 postcss autoprefixer @tailwindcss/forms @tailwindcss/typography');

echo "Hotovo!\n";
