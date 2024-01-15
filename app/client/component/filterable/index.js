import domd from 'domd'
import nav from 'lib/navigation'
import dispatcher from 'edispatcher'

export default element => {
	const d = domd(element)
	const slug = element.getAttribute('data-url')
	const key = element.getAttribute('data-key')
	const only_for = element.getAttribute('data-only-for')
	const checkboxes = element.querySelectorAll('li')

	dispatcher.on('filters_updated', (ev, data) => {
		for (const update_key in data) {
			if (key !== update_key) {
				continue
			}
			console.log(data[key])
			const value = data[key]
			checkboxes.forEach(el => {
				el.classList.remove('active')
				const el_value = el.querySelector('a').getAttribute('data-value')
				if (el_value === value) {
					el.classList.add('active')
				}
			})
		}
	})

	// Handle checkboxes set on load by url
	const params = parseQueryString(location.search)
	const values = params.getAll(`filters[${key}][]`)
	if (values) {
		const checkboxes = element.querySelectorAll('input[type="checkbox"]')
		checkboxes.forEach(checkbox => {
			checkbox.checked = values.includes(checkbox.value)
		})
	}

	d.on('keyup', 'input[type="text"]', (ev, el) => {
		const value = el.value.toLowerCase()

		checkboxes.forEach(function(item) {
			const label = item.querySelector('label').textContent.toLowerCase();
	    if (label.indexOf(value) > -1) {
        item.style.display = '';
	    } else {
        item.style.display = 'none';
	    }
		})
	})

	d.on('change', 'select', (ev, el) => {
		const option = el.options[el.selectedIndex]
		let query = nav.removeParam(location.search, key)
		nav.load(slug + '?' + query + ';' + key + '=' + option.value)
	})

	d.on('click', 'a', (ev, el) => {
		checkboxes.forEach(function(item) {
			item.classList.remove('active')
		})
		el.parentElement.classList.add('active')
		let query = nav.removeParam(location.search, `filters[${key}]`)
		const value = el.getAttribute('data-value')
		const el_only_for = el.getAttribute('data-only-for')
		if (el_only_for || only_for) {
			const params = parseQueryString(location.search)
			const fields = JSON.parse(el_only_for ? el_only_for : only_for)
			for (const key in fields) {
				const value = params.get(`filters[${key}]`)
				if (!fields[key].includes(value)) {
					query = nav.removeParam(query, `filters[${key}]`)
					query += ';filters[' + key + ']=' + fields[key][0]
					dispatcher.send('filters_updated', {[key]: fields[key][0]});
				}
			}
		}
		nav.load(slug + '?' + query + ';filters[' + key + ']=' + value)
		return false
	})

	d.on('click', 'input[type="checkbox"]', (ev, el) => {
		let filters = []
		const checkboxes = element.querySelectorAll('input[type="checkbox"]')
	  checkboxes.forEach(checkbox => {
      if (checkbox.checked) {
        filters.push(`filters[${key}][]=${encodeURIComponent(checkbox.value)}`)
      }
	  })

	  const filters_query = filters.join(';')
	  let query = nav.removeParam(location.search, `filters[${key}][]`)
		nav.load(slug + '?' + query + (filters_query ? ';' + filters_query : ''))
	})


	d.on('click', 'input[type="radio"]', (ev, el) => {
		const value = element.querySelector('input[type="radio"]:checked').value
		let query = nav.removeParam(location.search, `filters[${key}]`)
		nav.load(slug + '?' + query + (value ? ';' + 'filters[' + key + ']=' + value : ''))
	})

	dispatcher.on('counters_updated', (ev, counters) => {
		const links = element.querySelectorAll('a')
		links.forEach(lnk => {
			const key = lnk.getAttribute('data-value')
			const counter = lnk.parentElement.querySelector('counter')
			if (!key ||!counter) {
				return
			}
			switch (key) {
				case 'everywhere':
					counter.innerText = counters.total
					break
				case 'any':
					counter.innerText = counters.any_issues
					break
				case 'open':
					counter.innerText = counters.open_issues
					break
				case 'closed':
					counter.innerText = counters.closed_issues
					break
				default:
					counter.innerText = counters[key]
			}
		})
	})
	return () => {}
}

function parseQueryString(query) {
  const params = new URLSearchParams()
  const pairs = query.split(';')
  pairs.forEach(pair => {
    const [key, value] = pair.split('=')
    params.append(decodeURIComponent(key), decodeURIComponent(value))
  })
  return params
}
