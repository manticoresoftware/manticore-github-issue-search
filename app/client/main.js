import Navigation from 'app/lib/navigation.js'
import dispatcher from 'edispatcher'

// Init components
dispatcher.on('page_content_loaded', (ev, {ajax}) => {
  const element = ajax ? document.getElementById('page') : document
  const components = element.querySelectorAll('[data-component]')
  Array.prototype.forEach.call(components, (mount_point) => {
    const cn = mount_point.getAttribute('data-component')
    try {
      const cc = require('./component/' + cn + '/index.js').default
      cc(mount_point)
    } catch (e) {
      console.warn('Failed to initialize component ' + cn + '. ' + e)
    }
  })
})

Navigation.init()
dispatcher.send('page_content_loaded', { ajax: false, url: location.pathname + location.search })
