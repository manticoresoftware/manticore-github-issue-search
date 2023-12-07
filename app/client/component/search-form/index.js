import domd from 'domd'
import nav from 'lib/navigation'

export default element => {
	const d = domd(element)
	const slug = element.getAttribute('action')

	element.addEventListener('submit', (ev) => {
		ev.preventDefault()
		const value = element.querySelector('[name="query"]').value
		const query = nav.removeParam(location.search, 'query')
		nav.load(slug + '?' + query + ';query=' + value)
	})
	return () => {}
}
