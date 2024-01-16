import domd from 'domd'

const githubRepoRegex = /^(?:https:\/\/github\.com\/)?([a-zA-Z0-9_-]+\/[a-zA-Z0-9_.-]+)\/?$/;

export default element => {

	const d = domd(element)
	const inputEl = element.querySelector('input[type="text"]')
	d.on('submit', 'form', (ev, el) => {
		if (githubRepoRegex.test(inputEl.value)) {
			element.classList.remove('error')
			return true
		}
		element.classList.add('error')
		return false
	})
	return () => {}
}
