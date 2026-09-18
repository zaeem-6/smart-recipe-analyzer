# Smart Recipe Analyzer

A lightweight web app that suggests recipes based on ingredients you have, using an LLM via OpenRouter.

## How it works

Enter comma-separated ingredients → backend sends them to an LLM → returns 2-3 recipe
suggestions with instructions and nutritional info, displayed as cards.

## Tech stack

- **Frontend:** HTML, CSS, vanilla JavaScript (no framework, no build step)
- **Backend:** PHP (no framework, no database)
- **LLM:** OpenRouter API (`deepseek/deepseek-v4-flash-0731:free`)

## Setup

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Add your OpenRouter API key to `.env` (get one free at https://openrouter.ai/keys)
3. Run with PHP's built-in server:
   ```bash
   php -S localhost:8000
   ```
   Or with Docker:
   ```bash
   docker run -d --name recipe-app -p 8000:8000 -v "${PWD}:/app" -w /app php:8.2-cli-alpine php -S 0.0.0.0:8000
   ```
4. Open `http://localhost:8000` in your browser

## API

**POST** `/api/generate.php`

Request body:
```json
{ "ingredients": "chicken, garlic, rice, soy sauce" }
```

Response:
```json
{
  "success": true,
  "recipes": [
    {
      "name": "Garlic Butter Pasta",
      "ingredients": ["pasta", "garlic", "butter", "parmesan"],
      "instructions": ["Boil pasta...", "Saute garlic..."],
      "cookingTime": "20 minutes",
      "difficulty": "Easy",
      "nutrition": { "calories": 450, "protein": "12g", "carbs": "60g" }
    }
  ]
}
```

## Notes

- Uses a free OpenRouter model, so responses can take 20-45 seconds depending on
  provider load — this is expected, not a bug.
- No database or persistence — each request is stateless.
- If the ingredients provided aren't real/recognizable food items, the API returns
  an empty `recipes` array rather than inventing something.
