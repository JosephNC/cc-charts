const {
    Flex,
    FlexItem,
    FlexBlock,
    Spinner,
    SelectControl,
    __experimentalText: Text,
    __experimentalHeading: Heading,
} = wp.components;
const { __ } = wp.i18n;
const { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } = Recharts;

const ChartWidget = () => {

    const [ loading, setLoading ] = React.useState( true );
    const [ data, setData ] = React.useState( [] );
    const [ message, setMessage ] = React.useState( '' );

    const fetchData = async ( days = 7 ) => {
        setLoading( true );

        // Fetch data from rest api
        const response = await fetch( `${wpApiSettings.root}cc-charts/v1/data/${days}`, {
            headers: {
                'X-WP-Nonce': wpApiSettings.nonce // Authenticate the user
            }
        } );

        let responseData = await response.json();
        let responseMessage = '';

        if ( 
            ( typeof responseData.code !== 'undefined' && responseData.message !== 'undefined' ) ||
            responseData.length <= 0
        ) {
            responseMessage = responseData.message ?? '';
            responseData = [];
        }

        setData( responseData );

        setMessage( responseMessage );

        setLoading( false );
    }

    const getDataByDate = value => fetchData( value ).then();

    React.useEffect( () => fetchData().then(), [] );

    return (
        <Flex direction="column">
            <FlexBlock>
                <Flex>
                    <FlexItem><Heading level="4">{ __( 'Chart', 'cc-charts' ) }</Heading></FlexItem>
                    <FlexItem>
                        <SelectControl
                            onChange={ getDataByDate }
                            options={[
                                {
                                    disabled: true,
                                    label: __( 'Select an Option', 'cc-charts' ),
                                    value: ''
                                },
                                {
                                    label: __( 'Last 7 days', 'cc-charts' ),
                                    value: 7
                                },
                                {
                                    label: __( 'Last 15 days', 'cc-charts' ),
                                    value: 15
                                },
                                {
                                    label: __( 'A month ago', 'cc-charts' ),
                                    value: 30
                                }
                            ]}
                        />
                    </FlexItem>
                </Flex>
            </FlexBlock>

            <Flex justify="center" style={{
                height: '150px',
                width: '100%'
            }}>
                {
                    loading ?
                        <Flex justify="center" gap="0">
                            <FlexItem>
                                <Spinner />
                            </FlexItem>
                            <FlexItem>
                                <Text>{ __( 'Loading', 'cc-charts' ) }</Text>
                            </FlexItem>
                        </Flex>
                        :
                        (
                            data.length <= 0 ?
                                <Text>{ message.length <= 0 ? __( 'No data found.', 'cc-charts' ) : message }</Text> :
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
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis dataKey="name" />
                                        <YAxis />
                                        <Tooltip />
                                        <Legend />
                                        <Bar dataKey="pv" fill="#8884d8" />
                                        <Bar dataKey="uv" fill="#82ca9d" />
                                    </BarChart>
                                </ResponsiveContainer>
                        )
                }
            </Flex>
        </Flex>
    )
}

const root = ReactDOM.createRoot( document.querySelector( '#cc-charts_widget' ) );

root.render( <ChartWidget /> );