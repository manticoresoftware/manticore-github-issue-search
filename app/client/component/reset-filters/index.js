import domd from 'domd'
import dispatcher from 'edispatcher'

export default element => {
	const d = domd(element)

	dispatcher.on('search', (ev, {query}) => {
		element.href = location.pathname + `?query=${query}`
	})

	return () => {}
}
