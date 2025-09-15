
import { DndProvider } from 'react-dnd';
import { HTML5Backend } from 'react-dnd-html5-backend'
import './App.css'
import CallBack from './components/Callback';
import Dashboard from './components/Dashboard';
import LandingPage from './components/LandingPage'
import 'bootstrap/dist/css/bootstrap.min.css';
import {BrowserRouter as Router, Routes, Route} from 'react-router-dom'
function App() {


  return (
    <>
      <DndProvider backend={HTML5Backend}>
      <Router>
        <div>
          <Routes>
            <Route path ='/' element={<LandingPage/>}/>
            <Route path ='/auth/success' element={<CallBack/>}/>
            <Route path ='/dashboard' element={<Dashboard/>}/>
          </Routes>
        </div>
      </Router>
    </DndProvider>
    </>
  )
}

export default App
