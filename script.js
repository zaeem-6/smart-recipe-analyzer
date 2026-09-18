const form = document.querySelector('#recipe-form');
const ingredientsInput = document.querySelector('#ingredients');
const generateButton = document.querySelector('#generate-button');
const results = document.querySelector('#results');
const resultCount = document.querySelector('#result-count');

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  const ingredients = ingredientsInput.value.trim();
  if (!ingredients) {
    showMessage('Please enter at least one ingredient.', 'error');
    ingredientsInput.focus();
    return;
  }

  setLoading(true);

  try {
    const response = await fetch('api/generate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ingredients }),
    });

    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || 'Could not generate recipes right now.');
    }

    renderRecipes(Array.isArray(payload.recipes) ? payload.recipes : []);
  } catch (error) {
    const message = error instanceof TypeError
      ? 'We could not reach the recipe service. Please check your connection and try again.'
      : error.message;
    showMessage(message, 'error');
  } finally {
    setLoading(false);
  }
});

function setLoading(isLoading) {
  generateButton.disabled = isLoading;
  generateButton.querySelector('span:first-child').textContent = isLoading
    ? 'Generating recipes...'
    : 'Generate recipes';

  if (isLoading) {
    resultCount.textContent = '';
    results.innerHTML = '<div class="loading-state"><span class="spinner" aria-hidden="true"></span><p>Finding something delicious...</p></div>';
  }
}

function showMessage(message, type) {
  resultCount.textContent = '';
  results.innerHTML = '';
  const messageElement = document.createElement('div');
  messageElement.className = `message-state ${type}`;
  messageElement.textContent = message;
  results.append(messageElement);
}

function renderRecipes(recipes) {
  results.innerHTML = '';
  if (recipes.length === 0) {
    showMessage('No recipes came back this time. Try adding a few more ingredients.', 'error');
    return;
  }

  resultCount.textContent = `${recipes.length} ${recipes.length === 1 ? 'idea' : 'ideas'}`;
  recipes.forEach((recipe, index) => results.append(createRecipeCard(recipe, index)));
}

function createRecipeCard(recipe, index) {
  const card = document.createElement('article');
  card.className = 'recipe-card';
  card.style.setProperty('--card-index', index);

  const header = document.createElement('div');
  header.className = 'card-header';

  const number = document.createElement('span');
  number.className = 'recipe-number';
  number.textContent = String(index + 1).padStart(2, '0');

  const name = document.createElement('h3');
  name.textContent = recipe.name || 'Untitled recipe';

  const difficulty = document.createElement('span');
  difficulty.className = `difficulty ${String(recipe.difficulty || 'Easy').toLowerCase()}`;
  difficulty.textContent = recipe.difficulty || 'Easy';

  header.append(number, name, difficulty);
  card.append(header);

  const details = document.createElement('div');
  details.className = 'recipe-details';
  details.append(
    createDetail('COOKING TIME', recipe.cookingTime || 'Varies'),
    createDetail('NUTRITION', formatNutrition(recipe.nutrition)),
  );
  card.append(details);

  card.append(createListSection('Ingredients', recipe.ingredients, false));
  card.append(createListSection('Method', recipe.instructions, true));
  return card;
}

function createDetail(label, value) {
  const detail = document.createElement('div');
  detail.className = 'detail';
  const detailLabel = document.createElement('span');
  detailLabel.className = 'detail-label';
  detailLabel.textContent = label;
  const detailValue = document.createElement('strong');
  detailValue.textContent = value;
  detail.append(detailLabel, detailValue);
  return detail;
}

function formatNutrition(nutrition = {}) {
  const calories = nutrition.calories ?? '—';
  const protein = nutrition.protein || '—';
  const carbs = nutrition.carbs || '—';
  return `${calories} cal · ${protein} protein · ${carbs} carbs`;
}

function createListSection(title, items, numbered) {
  const section = document.createElement('div');
  section.className = 'list-section';
  const heading = document.createElement('h4');
  heading.textContent = title;
  const list = document.createElement(numbered ? 'ol' : 'ul');
  list.className = numbered ? 'instructions' : 'ingredients-list';

  const safeItems = Array.isArray(items) ? items : [];
  safeItems.forEach((item) => {
    const listItem = document.createElement('li');
    listItem.textContent = String(item);
    list.append(listItem);
  });
  section.append(heading, list);
  return section;
}
