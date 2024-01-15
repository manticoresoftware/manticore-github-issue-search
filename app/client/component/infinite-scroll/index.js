import domd from 'domd'
import nav from 'lib/navigation'
import dispatcher from 'edispatcher'

const opts = {
  credentials: 'same-origin',
  method: 'GET',
  headers: {
    'X-Requested-With': 'navigation'
  }
}

export default element => {
  const d = domd(element)
  let offset = 0
  let isEnd = false
  let isProcessing = false
  dispatcher.on('filters_updated', (ev, data) => {
  	offset = 0
  	isEnd = false
  })

  // Scroll event listener to trigger fetching new items
  element.addEventListener('scroll', () => {
  	if (isEnd || isProcessing) {
  		return
  	}

    if (isNearBottom(element)) {
    	isProcessing = true
    	offset += 50

    	const path = window.location.pathname
    	const query = nav.removeParam(location.search, 'offset')
    	const url = path + '?' + query + ';' + 'offset' + '=' + offset

      fetch(url, opts).then(res => res.text()).then(body => {
        window.requestAnimationFrame(() => {
					// Use DOMParser to parse the response body into a document
					const parser = new DOMParser()
					const doc = parser.parseFromString(body, 'text/html')

					// Query the document for elements with the tag 'card'
					const cards = doc.querySelectorAll('card')
					isEnd = cards.length === 0
					// Append each 'card' to the element
					cards.forEach(card => {
						element.appendChild(card)
					})
					isProcessing = false
        })
      })
    }
  })

  // Return a function to allow manual stopping of the polling
  return () => {}
}

// Function to check if we are close to the bottom of the page
function isNearBottom(container) {
  const scrollTop = container.scrollTop // Distance scrolled from the top
  const containerHeight = container.clientHeight // Visible height of the container
  const scrollHeight = container.scrollHeight // Total scrollable content height

  // Check if the container's scroll position is within 100px of the bottom
  return scrollTop + containerHeight >= scrollHeight - 100
}
