import domd from 'domd'
import nav from 'lib/navigation'
import dispatcher from 'edispatcher'

export default element => {
	const d = domd(element)
	const api_url = element.getAttribute('data-api-url')
	const slug = element.getAttribute('action')

	const inputEl = element.querySelector('[name="query"]')
	const autocompleteEl = element.querySelector('#autocomplete')
	const configureEl = element.querySelector('#configure')

	// Define default values
	const defaultConfig = {
		fuzziness: '2',
		expansion_limit: '4',
		append: true,
		prepend: false,
		layouts: ['ru', 'us', 'ua'],
	};

	// Function to load values from localStorage or use defaults
	function loadConfig() {
		const storedConfig = JSON.parse(localStorage.getItem('autocompleteConfig')) || {};
		return { ...defaultConfig, ...storedConfig };
	}

	// Function to save config to localStorage
	function saveConfig(config) {
		localStorage.setItem('autocompleteConfig', JSON.stringify(config));
	}

	// Load the initial configuration
	let currentConfig = loadConfig();

	// Set up input elements
	const configureInputEls = configureEl.querySelectorAll('input');
	for (const configureInputEl of configureInputEls) {
		const inputName = configureInputEl.name;

		// Set initial value from loaded config
		if (configureInputEl.type === 'checkbox') {
			const configValue = currentConfig[inputName];
			if (inputName.endsWith('[]')) {
				const baseInputName = inputName.slice(0, -2);
				if (Array.isArray(currentConfig[baseInputName])) {
					configureInputEl.checked = currentConfig[baseInputName].includes(configureInputEl.value);
				} else {
					configureInputEl.checked = false;
				}
			} else {
				configureInputEl.checked = configValue || false;
			}
		} else if (configureInputEl.type === 'radio') {
			configureInputEl.checked = configureInputEl.value === (currentConfig[inputName] || '');
		} else {
			configureInputEl.value = currentConfig[inputName] || '';
		}

		// Add event listener to save changes
		configureInputEl.addEventListener('change', (event) => {
			if (inputName.endsWith('[]')) {
				const cleanInputName = inputName.replace(/\[\]$/, '');
				if (!currentConfig[cleanInputName]) {
					currentConfig[cleanInputName] = [];
				}
				if (event.target.checked) {
					if (!currentConfig[cleanInputName].includes(event.target.value)) {
						currentConfig[cleanInputName].push(event.target.value);
					}
				} else {
					currentConfig[cleanInputName] = currentConfig[cleanInputName].filter(value => value !== event.target.value);
				}
			} else if (event.target.type === 'radio') {
				currentConfig[inputName] = event.target.value;
			} else {
				currentConfig[inputName] = event.target.checked ? true : false
			}

			saveConfig(currentConfig)
		})
	}

	let hasActiveSuggestion = false
	let currentActiveIndex = -1

	d.on('click', '.icon-configure', (ev) => {
		ev.preventDefault()
		autocompleteEl.classList.remove('visible')
		configureEl.classList.toggle('visible')
	})

	element.addEventListener('submit', (ev) => {
		ev.preventDefault()
		const value = inputEl.value
		const query = nav.removeParam(location.search, 'query')
		nav.load(slug + '?' + query + ';query=' + value)
		dispatcher.send('search', {query: value})
	})

	const reset_fn = (ev) => {
		if (hasActiveSuggestion && currentActiveIndex !== -1) {
			suggestions[currentActiveIndex].element.classList.remove('active')
		}
		hasActiveSuggestion = false
		currentActiveIndex = -1
	}

	autocompleteEl.addEventListener('mouseenter', reset_fn)
	autocompleteEl.addEventListener('mouseleave', reset_fn)
	autocompleteEl.addEventListener('mousemove', (ev) => {
		reset_fn(ev)
		const hoveredElement = ev.target
		if (hoveredElement) {
			hoveredElement.classList.add('active')
			hasActiveSuggestion = true
			const liElements = autocompleteEl.querySelectorAll('li');
			currentActiveIndex = Array.from(liElements).findIndex(li => li === hoveredElement);
		}
	})

	d.on('click', 'li', (ev, el) => {
		inputEl.value = el.textContent
		element.submit()
	})

	inputEl.addEventListener('focus', (ev) => {
		autocompleteEl.classList.add('visible')
		configureEl.classList.remove('visible')
	})

	inputEl.addEventListener('blur', (ev) => {
		setTimeout(() => {
			autocompleteEl.classList.remove('visible')
		}, 150)
	})

	let suggestions = []
	const iconSVG = element.querySelector('.icon').innerHTML
	const updateSuggestions = result => {
		suggestions = []
		const listEl = document.createElement('ul')
		for (const item of result) {
			const li = document.createElement('li')
			li.innerHTML = `<span class="icon">${iconSVG}</span><span class="text">${item.query}</span>`
			listEl.appendChild(li)
			suggestions.push({query: item.query, element: li})
		}

		while (autocompleteEl.firstChild) {
			autocompleteEl.removeChild(autocompleteEl.firstChild)
		}
		autocompleteEl.appendChild(listEl)
	}

	let debounceTimer
	let activeRequest
	let previousQuery = ''

	function debounce(func, delay) {
		let timer
		return function(...args) {
			clearTimeout(timer)
			timer = setTimeout(() => func.apply(this, args), delay)
		}
	}

	const fetchAutocomplete = async (query) => {
		if (query === previousQuery) return
		previousQuery = query

		if (activeRequest) {
			activeRequest.abort()
		}

		const controller = new AbortController()
		activeRequest = controller

		try {
			const response = await fetch(api_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					query: query,

					config: currentConfig
				}),
				signal: controller.signal
			});

			const [err, result] = await response.json();

			updateSuggestions(result)

		} catch (error) {
			if (error.name === 'AbortError') {
				console.log('Fetch aborted')
			} else {
				console.error('Fetch error:', error)
			}
		} finally {
			activeRequest = null }
	}

	const debouncedFetchAutocomplete = debounce((query) => {
		if (query !== previousQuery) {
			fetchAutocomplete(query)
		}
	}, 300)

	inputEl.addEventListener('input', (ev) => {
		const query = ev.target.value.trim()
		if (query.length > 0) {
			autocompleteEl.classList.add('visible')
			debouncedFetchAutocomplete(query)
		} else {
			autocompleteEl.classList.remove('visible')
			clearTimeout(debounceTimer)
		}
	})

	inputEl.addEventListener('keydown', (ev) => {
		autocompleteEl.classList.add('keyboard-active')
		if (ev.key === 'Escape' || ev.key === 'Enter') {
			autocompleteEl.classList.remove('visible')
			hasActiveSuggestion = false
		} else if (ev.key === 'ArrowUp' || ev.key === 'ArrowDown') {
			ev.preventDefault()
			const currentValue = inputEl.value
			const currentIndex = currentActiveIndex > -1
				? currentActiveIndex
				: suggestions.findIndex(suggestion => suggestion.query === currentValue)
			let newIndex

			if (ev.key === 'ArrowUp') {
				newIndex = currentIndex > 0 ? currentIndex - 1 : suggestions.length - 1
			} else {
				newIndex = currentIndex < suggestions.length - 1 ? currentIndex + 1 : 0
			}

			inputEl.value = suggestions[newIndex].query

			if (hasActiveSuggestion && currentActiveIndex !== -1) {
				suggestions[currentActiveIndex].element.classList.remove('active')
			}

			suggestions[newIndex].element.classList.add('active')
			currentActiveIndex = newIndex
			hasActiveSuggestion = true
		}
	})
	return () => {}
}
