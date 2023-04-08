"use strict";

const {BarChart, Bar, Cell, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer} = Recharts;

const ChartWidget = () => {

    const [loading, setLoading] = React.useState(true)
    const [data, setData] = React.useState([])

    const fetchData = async (days = 7) => {
        setLoading(true)

        // Fetch data from rest api
        const response = await fetch(`/wp-json/cc-charts/v1/data/${days}`)
        const responseData = await response.json()

        // Update state
        setData(responseData)

        setLoading(false)
    }
    const getDataByDate = (event) => fetchData( event.target.value ).then()

    React.useEffect(() => {
        fetchData().then()
    }, [])

    return (
        <>
            <div className="cc-charts_widget_top_section">
                <h4>Chart</h4>
                <label>
                    <select onClick={getDataByDate}>
                        <option value={7}>Last 7 days</option>
                        <option value={15}>Last 15 days</option>
                        <option value={30}>A month ago</option>
                    </select>
                </label>
            </div>

            {
                loading ? <div>Loading...</div> : (
                    data.length <= 0 ? <div>No data found.</div> :
                        <div className="cc-charts_widget_graph_section">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart
                                    height={150}
                                    data={data}
                                    margin={{
                                        top: 0,
                                        right: 30,
                                        left: 0,
                                        bottom: 0,
                                    }}
                                >
                                    <CartesianGrid strokeDasharray="3 3"/>
                                    <XAxis dataKey="name"/>
                                    <YAxis/>
                                    <Tooltip/>
                                    <Legend/>
                                    <Bar dataKey="pv" fill="#8884d8"/>
                                    <Bar dataKey="uv" fill="#82ca9d"/>
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                )
            }
        </>
    )
}
const root = ReactDOM.createRoot(document.querySelector('#cc-charts_widget'))
root.render(<ChartWidget/>)