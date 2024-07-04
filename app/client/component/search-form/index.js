import domd from 'domd'
import nav from 'lib/navigation'
import dispatcher from 'edispatcher'

export default element => {
	const d = domd(element)
	const api_url = element.getAttribute('data-api-url')
	const slug = element.getAttribute('action')

	const inputEl = element.querySelector('[name="query"]')
	const autocompleteEl = element.querySelector('#autocomplete')
	element.addEventListener('submit', (ev) => {
		ev.preventDefault()
		const value = inputEl.value
		const query = nav.removeParam(location.search, 'query')
		nav.load(slug + '?' + query + 'query=' + value)
		dispatcher.send('search', {query: value})
	})

	d.on('click', 'li', (ev, el) => {
		inputEl.value = el.textContent
		element.submit()
	})

	inputEl.addEventListener('focus', (ev) => {
		autocompleteEl.classList.add('visible')
	})

	inputEl.addEventListener('blur', (ev) => {
		setTimeout(() => {
			autocompleteEl.classList.remove('visible')
		}, 150)
	})

	const iconSVG = element.querySelector('.icon').innerHTML
	const updateSuggestions = result => {
		const listEl = document.createElement('ul')
		for (const item of result) {
			const li = document.createElement('li')
			li.innerHTML = `<span class="icon">${iconSVG}</span><span class="text">${item.query}</span>`
			listEl.appendChild(li)
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
			const response = await fetch(`${api_url}?query=${encodeURIComponent(query)}`, {
				signal: controller.signal
			})
			const [err, result] = await response.json()

			updateSuggestions(result)

		} catch (error) {
			if (error.name === 'AbortError') {
				console.log('Fetch aborted')
			} else {
				console.error('Fetch error:', error)
			}
		} finally {
			activeRequest = null
		}
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
		if (ev.key === 'Escape' || ev.key === 'Enter') {
			autocompleteEl.classList.remove('visible')
		}
	})
	return () => {}
}
