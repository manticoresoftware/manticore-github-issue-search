import domd from 'domd'
import nav from 'lib/navigation'

const pollDuration = 1000
export default element => {
  const d = domd(element)
  const api_url = element.getAttribute('data-api-url')

  let pollingInterval = null
  let percentage = 0
  let step = 0
  let duration = 1
  const updateProgressBar = (percentage, duration) => {
  	element.style.setProperty('--progress-duration', `${duration}s`)
  	element.style.setProperty('--progress-value', (percentage + step) / 100)
  }

  const fetchData = async () => {
    try {
      const response = await fetch(api_url)
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      const [err, result] = await response.json()
      if (!step) {
      	step = 1 / parseInt(result.expected_issues / 100, 10) + 1
      } else {
      	duration = parseInt(Math.min(100, result.expected_issues) / 6, 10)
      }
      if (result.indexed_percentage > percentage) {
      	nav.load(location.href)
      }
      percentage = result.indexed_percentage
      updateProgressBar(percentage, duration)
      if (!result.is_indexing) {
      	element.classList.remove('progress-bar')
        clearInterval(pollingInterval)
        console.log('Indexing complete, stopped polling.')
      }
    } catch (error) {
      console.error('Failed to fetch data:', error)
    }
  }

  // Start polling
  fetchData()
  pollingInterval = setInterval(fetchData, pollDuration)

  // Return a function to allow manual stopping of the polling
  return () => {
    clearInterval(pollingInterval)
  }
}
