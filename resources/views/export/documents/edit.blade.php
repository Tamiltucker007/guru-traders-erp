<x-app-layout>
    <x-slot name="header">Edit Export Document {{ $document->doc_num }}</x-slot>

    <x-ui.card :title="'Edit '.$document->doc_num" variant="primary">
        <x-slot name="actions">
            <a href="{{ route('export.documents.show', $document) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </x-slot>

        <p class="text-body-secondary small mb-4">
            Shipment logistics details — printed on the Delivery Challan, Export Invoice and E-way Bill. None of
            this comes from the Order Confirmation automatically; fill it in once the shipment is being packed.
        </p>

        <form action="{{ route('export.documents.update', $document) }}" method="POST">
            @csrf
            @method('PUT')

            <h6 class="fw-semibold mb-2">Shipment</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Incoterm</label>
                    <select name="incoterm_id" class="form-select @error('incoterm_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach($incoterms as $id => $code)
                            <option value="{{ $id }}" @selected((string) old('incoterm_id', $document->incoterm_id) === (string) $id)>{{ $code }}</option>
                        @endforeach
                    </select>
                    @error('incoterm_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Shipment Method</label>
                    <select name="shipment_method_id" class="form-select @error('shipment_method_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach($shipmentMethods as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('shipment_method_id', $document->shipment_method_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('shipment_method_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Port of Loading</label>
                    <select name="port_of_loading_id" class="form-select @error('port_of_loading_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach($ports as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('port_of_loading_id', $document->port_of_loading_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('port_of_loading_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Port of Discharge</label>
                    <select name="port_of_discharge_id" class="form-select @error('port_of_discharge_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach($ports as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('port_of_discharge_id', $document->port_of_discharge_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('port_of_discharge_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Final Destination</label>
                    <input type="text" name="final_destination" value="{{ old('final_destination', $document->final_destination) }}"
                           class="form-control @error('final_destination') is-invalid @enderror" placeholder="Defaults to Port of Discharge">
                    @error('final_destination') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Shipment Date</label>
                    <input type="date" name="shipment_date" value="{{ old('shipment_date', $document->shipment_date?->toDateString()) }}"
                           class="form-control @error('shipment_date') is-invalid @enderror">
                    @error('shipment_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <h6 class="fw-semibold mb-2">Invoice Reference</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Invoice No.</label>
                    <input type="text" name="invoice_no" value="{{ old('invoice_no', $document->invoice_no) }}"
                           class="form-control @error('invoice_no') is-invalid @enderror" placeholder="e.g. EXP25269001">
                    @error('invoice_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice Date</label>
                    <input type="date" name="invoice_date" value="{{ old('invoice_date', $document->invoice_date?->toDateString()) }}"
                           class="form-control @error('invoice_date') is-invalid @enderror">
                    @error('invoice_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Exporter's Ref</label>
                    <input type="text" name="exporter_ref" value="{{ old('exporter_ref', $document->exporter_ref) }}"
                           class="form-control @error('exporter_ref') is-invalid @enderror">
                    @error('exporter_ref') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <h6 class="fw-semibold mb-2">Packing List — Header Details <span class="text-body-secondary fw-normal">(Format C, for customs)</span></h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Buyer's Ref No.</label>
                    <input type="text" name="buyer_ref_no" value="{{ old('buyer_ref_no', $document->buyer_ref_no) }}"
                           class="form-control @error('buyer_ref_no') is-invalid @enderror">
                    @error('buyer_ref_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Buyer's Ref Date</label>
                    <input type="date" name="buyer_ref_date" value="{{ old('buyer_ref_date', $document->buyer_ref_date?->toDateString()) }}"
                           class="form-control @error('buyer_ref_date') is-invalid @enderror">
                    @error('buyer_ref_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Other Reference(s)</label>
                    <input type="text" name="other_reference" value="{{ old('other_reference', $document->other_reference) }}"
                           class="form-control @error('other_reference') is-invalid @enderror">
                    @error('other_reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Consignee Name <span class="text-body-secondary fw-normal">(leave blank to use the Buyer)</span></label>
                    <input type="text" name="consignee_name" value="{{ old('consignee_name', $document->consignee_name) }}"
                           class="form-control @error('consignee_name') is-invalid @enderror">
                    @error('consignee_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Consignee Address</label>
                    <input type="text" name="consignee_address" value="{{ old('consignee_address', $document->consignee_address) }}"
                           class="form-control @error('consignee_address') is-invalid @enderror">
                    @error('consignee_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pre-Carriage By</label>
                    <input type="text" name="pre_carriage_by" value="{{ old('pre_carriage_by', $document->pre_carriage_by) }}"
                           class="form-control @error('pre_carriage_by') is-invalid @enderror" placeholder="e.g. AIR, ROAD">
                    @error('pre_carriage_by') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Place of Receipt by Pre-Carrier</label>
                    <input type="text" name="place_of_receipt" value="{{ old('place_of_receipt', $document->place_of_receipt) }}"
                           class="form-control @error('place_of_receipt') is-invalid @enderror">
                    @error('place_of_receipt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vessel / Flight No.</label>
                    <input type="text" name="vessel_flight_no" value="{{ old('vessel_flight_no', $document->vessel_flight_no) }}"
                           class="form-control @error('vessel_flight_no') is-invalid @enderror">
                    @error('vessel_flight_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Country of Origin</label>
                    <input type="text" name="country_of_origin" value="{{ old('country_of_origin', $document->country_of_origin) }}"
                           class="form-control @error('country_of_origin') is-invalid @enderror">
                    @error('country_of_origin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Country of Final Destination is read from the Buyer's own country automatically.</div>
                </div>
            </div>

            <h6 class="fw-semibold mb-2">Forwarder / Transport <span class="text-body-secondary fw-normal">(Delivery Challan)</span></h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Forwarder / CHA Name</label>
                    <input type="text" name="forwarder_name" value="{{ old('forwarder_name', $document->forwarder_name) }}"
                           class="form-control @error('forwarder_name') is-invalid @enderror">
                    @error('forwarder_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label">Forwarder Address</label>
                    <input type="text" name="forwarder_address" value="{{ old('forwarder_address', $document->forwarder_address) }}"
                           class="form-control @error('forwarder_address') is-invalid @enderror">
                    @error('forwarder_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Vehicle / Tempo No.</label>
                    <input type="text" name="vehicle_no" value="{{ old('vehicle_no', $document->vehicle_no) }}"
                           class="form-control @error('vehicle_no') is-invalid @enderror">
                    @error('vehicle_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Driver Cell No.</label>
                    <input type="text" name="driver_cell" value="{{ old('driver_cell', $document->driver_cell) }}"
                           class="form-control @error('driver_cell') is-invalid @enderror">
                    @error('driver_cell') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <h6 class="fw-semibold mb-2">Marks &amp; Packages <span class="text-body-secondary fw-normal">(Delivery Challan)</span></h6>
            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label class="form-label">Marks &amp; Nos.</label>
                    <textarea name="marks_and_numbers" rows="4"
                              class="form-control @error('marks_and_numbers') is-invalid @enderror"
                              placeholder="Buyer name, destination, carton number ranges...">{{ old('marks_and_numbers', $document->marks_and_numbers) }}</textarea>
                    @error('marks_and_numbers') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Total Cartons</label>
                    <input type="number" min="0" name="total_cartons" value="{{ old('total_cartons', $document->total_cartons) }}"
                           class="form-control @error('total_cartons') is-invalid @enderror">
                    @error('total_cartons') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Package Kind</label>
                    <input type="text" name="package_kind" value="{{ old('package_kind', $document->package_kind) }}"
                           class="form-control @error('package_kind') is-invalid @enderror">
                    @error('package_kind') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <h6 class="fw-semibold mb-2">Bill of Lading (Draft)</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Booking No.</label>
                    <input type="text" name="booking_no" value="{{ old('booking_no', $document->booking_no) }}"
                           class="form-control @error('booking_no') is-invalid @enderror">
                    @error('booking_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">B/L No.</label>
                    <input type="text" name="bl_no" value="{{ old('bl_no', $document->bl_no) }}"
                           class="form-control @error('bl_no') is-invalid @enderror">
                    @error('bl_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Voy. No.</label>
                    <input type="text" name="voyage_no" value="{{ old('voyage_no', $document->voyage_no) }}"
                           class="form-control @error('voyage_no') is-invalid @enderror">
                    @error('voyage_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">For Transshipment To</label>
                    <input type="text" name="transshipment_port" value="{{ old('transshipment_port', $document->transshipment_port) }}"
                           class="form-control @error('transshipment_port') is-invalid @enderror">
                    @error('transshipment_port') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notify Party Name <span class="text-body-secondary fw-normal">(leave blank to use the Consignee)</span></label>
                    <input type="text" name="notify_party_name" value="{{ old('notify_party_name', $document->notify_party_name) }}"
                           class="form-control @error('notify_party_name') is-invalid @enderror">
                    @error('notify_party_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notify Party Address</label>
                    <input type="text" name="notify_party_address" value="{{ old('notify_party_address', $document->notify_party_address) }}"
                           class="form-control @error('notify_party_address') is-invalid @enderror">
                    @error('notify_party_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label">Goods Description <span class="text-body-secondary fw-normal">(printed after "SAID TO CONTAIN ... CARTONS CONTAINING")</span></label>
                    <input type="text" name="goods_description" value="{{ old('goods_description', $document->goods_description) }}"
                           class="form-control @error('goods_description') is-invalid @enderror"
                           placeholder="e.g. POWERLOOM WOVEN READYMADE GARMENTS, SAREES, PP BAGS, LADIES PURSE ETC.">
                    @error('goods_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Measurement (CBM)</label>
                    <input type="number" step="0.001" min="0" name="total_measurement" value="{{ old('total_measurement', $document->total_measurement) }}"
                           class="form-control @error('total_measurement') is-invalid @enderror">
                    @error('total_measurement') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Freight Terms</label>
                    <select name="freight_terms" class="form-select @error('freight_terms') is-invalid @enderror">
                        @foreach(['PREPAID', 'COLLECT'] as $term)
                            <option value="{{ $term }}" @selected(old('freight_terms', $document->freight_terms) === $term)>{{ $term }}</option>
                        @endforeach
                    </select>
                    @error('freight_terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">EX. Rate</label>
                    <input type="text" name="ex_rate" value="{{ old('ex_rate', $document->ex_rate) }}"
                           class="form-control @error('ex_rate') is-invalid @enderror">
                    @error('ex_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Freight Prepaid At</label>
                    <input type="text" name="freight_prepaid_at" value="{{ old('freight_prepaid_at', $document->freight_prepaid_at) }}"
                           class="form-control @error('freight_prepaid_at') is-invalid @enderror">
                    @error('freight_prepaid_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Freight Payable At</label>
                    <input type="text" name="freight_payable_at" value="{{ old('freight_payable_at', $document->freight_payable_at) }}"
                           class="form-control @error('freight_payable_at') is-invalid @enderror">
                    @error('freight_payable_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Prepaid In</label>
                    <input type="text" name="total_prepaid_in" value="{{ old('total_prepaid_in', $document->total_prepaid_in) }}"
                           class="form-control @error('total_prepaid_in') is-invalid @enderror">
                    @error('total_prepaid_in') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. of Original B(s)/L</label>
                    <input type="text" name="no_of_original_bls" value="{{ old('no_of_original_bls', $document->no_of_original_bls) }}"
                           class="form-control @error('no_of_original_bls') is-invalid @enderror" placeholder="e.g. 3/THREE">
                    @error('no_of_original_bls') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Place of Issue</label>
                    <input type="text" name="bl_place_of_issue" value="{{ old('bl_place_of_issue', $document->bl_place_of_issue) }}"
                           class="form-control @error('bl_place_of_issue') is-invalid @enderror">
                    @error('bl_place_of_issue') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date of Issue</label>
                    <input type="date" name="bl_date_of_issue" value="{{ old('bl_date_of_issue', $document->bl_date_of_issue?->toDateString()) }}"
                           class="form-control @error('bl_date_of_issue') is-invalid @enderror">
                    @error('bl_date_of_issue') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <h6 class="fw-semibold mb-2">CIF Value <span class="text-body-secondary fw-normal">(only if the incoterm needs it — manual entry, no auto-calculation yet)</span></h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Freight Amount</label>
                    <input type="number" step="0.01" min="0" name="freight_amount" value="{{ old('freight_amount', $document->freight_amount) }}"
                           class="form-control @error('freight_amount') is-invalid @enderror">
                    @error('freight_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Insurance Amount</label>
                    <input type="number" step="0.01" min="0" name="insurance_amount" value="{{ old('insurance_amount', $document->insurance_amount) }}"
                           class="form-control @error('insurance_amount') is-invalid @enderror">
                    @error('insurance_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gross Weight (kg)</label>
                    <input type="number" step="0.001" min="0" name="gross_weight" value="{{ old('gross_weight', $document->gross_weight) }}"
                           class="form-control @error('gross_weight') is-invalid @enderror">
                    @error('gross_weight') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Net Weight (kg)</label>
                    <input type="number" step="0.001" min="0" name="net_weight" value="{{ old('net_weight', $document->net_weight) }}"
                           class="form-control @error('net_weight') is-invalid @enderror">
                    @error('net_weight') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Carton / Bale Dimension</label>
                    <input type="text" name="carton_dimensions" value="{{ old('carton_dimensions', $document->carton_dimensions) }}"
                           class="form-control @error('carton_dimensions') is-invalid @enderror" placeholder="e.g. 25 X 15 X 20">
                    @error('carton_dimensions') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <h6 class="fw-semibold mb-2">
                Cartons <span class="text-body-secondary fw-normal">(Packing List Formats B &amp; C — what's physically packed in each carton)</span>
            </h6>
            <div id="cartons-wrap" class="mb-4">
                @php
                    $cartonRows = old('cartons');

                    if ($cartonRows === null) {
                        $cartonRows = $document->cartons->map(fn ($carton) => [
                            'carton_no'    => $carton->carton_no,
                            'net_weight'   => $carton->net_weight,
                            'gross_weight' => $carton->gross_weight,
                            'dimensions'   => $carton->dimensions,
                            'lines'        => $carton->lines->map(fn ($line) => [
                                'description' => $line->description,
                                'unit'        => $line->unit,
                                'qty'         => $line->qty,
                            ])->all(),
                        ])->all();
                    }
                @endphp

                <div id="carton-blocks">
                    @foreach($cartonRows as $ci => $carton)
                        <div class="border rounded p-3 mb-3" data-carton-block>
                            <div class="row g-2 mb-2">
                                <div class="col-md-3">
                                    <label class="form-label small">Carton / Bale No.</label>
                                    <input type="text" name="cartons[{{ $ci }}][carton_no]" value="{{ $carton['carton_no'] ?? '' }}"
                                           class="form-control form-control-sm" placeholder="e.g. 1391">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Net Weight</label>
                                    <input type="number" step="0.001" min="0" name="cartons[{{ $ci }}][net_weight]" value="{{ $carton['net_weight'] ?? '' }}"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Gross Weight</label>
                                    <input type="number" step="0.001" min="0" name="cartons[{{ $ci }}][gross_weight]" value="{{ $carton['gross_weight'] ?? '' }}"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Dimensions</label>
                                    <input type="text" name="cartons[{{ $ci }}][dimensions]" value="{{ $carton['dimensions'] ?? '' }}"
                                           class="form-control form-control-sm" placeholder="25 X 15 X 20">
                                </div>
                            </div>

                            <table class="table table-sm align-middle mb-2" data-carton-lines>
                                <thead>
                                    <tr>
                                        <th style="min-width:220px">Description</th>
                                        <th style="width:110px">Unit</th>
                                        <th style="width:100px">Qty</th>
                                        <th style="width:36px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($carton['lines'] ?? []) as $li => $line)
                                        <tr data-line-row>
                                            <td>
                                                <input type="text" name="cartons[{{ $ci }}][lines][{{ $li }}][description]"
                                                       value="{{ $line['description'] ?? '' }}" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <input type="text" name="cartons[{{ $ci }}][lines][{{ $li }}][unit]"
                                                       value="{{ $line['unit'] ?? 'PCS' }}" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <input type="number" min="0" name="cartons[{{ $ci }}][lines][{{ $li }}][qty]"
                                                       value="{{ $line['qty'] ?? '' }}" class="form-control form-control-sm">
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-line" aria-label="Remove line">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr data-line-row>
                                            <td><input type="text" name="cartons[{{ $ci }}][lines][0][description]" class="form-control form-control-sm"></td>
                                            <td><input type="text" name="cartons[{{ $ci }}][lines][0][unit]" value="PCS" class="form-control form-control-sm"></td>
                                            <td><input type="number" min="0" name="cartons[{{ $ci }}][lines][0][qty]" class="form-control form-control-sm"></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-line" aria-label="Remove line">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary js-add-line">
                                    <i class="bi bi-plus-lg me-1"></i>Add line
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger js-remove-carton">
                                    <i class="bi bi-trash me-1"></i>Remove carton
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary" id="add-carton">
                    <i class="bi bi-plus-lg me-1"></i>Add carton
                </button>
                <div class="form-text">Blank cartons (no carton number) and blank lines (no description) are not saved.</div>

                <template id="carton-block-template">
                    <div class="border rounded p-3 mb-3" data-carton-block>
                        <div class="row g-2 mb-2">
                            <div class="col-md-3">
                                <label class="form-label small">Carton / Bale No.</label>
                                <input type="text" name="cartons[__CI__][carton_no]" class="form-control form-control-sm" placeholder="e.g. 1391">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Net Weight</label>
                                <input type="number" step="0.001" min="0" name="cartons[__CI__][net_weight]" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Gross Weight</label>
                                <input type="number" step="0.001" min="0" name="cartons[__CI__][gross_weight]" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Dimensions</label>
                                <input type="text" name="cartons[__CI__][dimensions]" class="form-control form-control-sm" placeholder="25 X 15 X 20">
                            </div>
                        </div>

                        <table class="table table-sm align-middle mb-2" data-carton-lines>
                            <thead>
                                <tr>
                                    <th style="min-width:220px">Description</th>
                                    <th style="width:110px">Unit</th>
                                    <th style="width:100px">Qty</th>
                                    <th style="width:36px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr data-line-row>
                                    <td><input type="text" name="cartons[__CI__][lines][0][description]" class="form-control form-control-sm"></td>
                                    <td><input type="text" name="cartons[__CI__][lines][0][unit]" value="PCS" class="form-control form-control-sm"></td>
                                    <td><input type="number" min="0" name="cartons[__CI__][lines][0][qty]" class="form-control form-control-sm"></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-line" aria-label="Remove line">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary js-add-line">
                                <i class="bi bi-plus-lg me-1"></i>Add line
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger js-remove-carton">
                                <i class="bi bi-trash me-1"></i>Remove carton
                            </button>
                        </div>
                    </div>
                </template>

                <template id="carton-line-template">
                    <tr data-line-row>
                        <td><input type="text" name="" class="form-control form-control-sm"></td>
                        <td><input type="text" name="" value="PCS" class="form-control form-control-sm"></td>
                        <td><input type="number" min="0" name="" class="form-control form-control-sm"></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-line" aria-label="Remove line">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </td>
                    </tr>
                </template>
            </div>

            <div class="col-12 mb-4">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks', $document->remarks) }}</textarea>
                @error('remarks') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save
                </button>
            </div>
        </form>
    </x-ui.card>

    {{-- Must stay inside x-app-layout: @push after the closing tag never reaches @stack. --}}
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        /* ------------------------------------------------------------------ *
         * Cartons (Packing List Formats B & C) — two-level repeater: cartons,
         * each with its own repeatable line items. Field names carry both
         * indexes (cartons[ci][lines][li][field]), so both levels are
         * reindexed after any add/remove or a later row would silently
         * overwrite an earlier one on submit.
         * ------------------------------------------------------------------ */
        const blocksWrap    = document.getElementById('carton-blocks');
        const addCartonBtn  = document.getElementById('add-carton');
        const cartonTemplate = document.getElementById('carton-block-template');
        const lineTemplate   = document.getElementById('carton-line-template');

        if (! blocksWrap || ! addCartonBtn) return;

        function reindexCarton(block, ci) {
            block.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/cartons\[\d+\]/, 'cartons[' + ci + ']');
            });

            reindexLines(block, ci);
        }

        function reindexLines(block, ci) {
            block.querySelectorAll('[data-line-row]').forEach(function (row, li) {
                row.querySelectorAll('[name]').forEach(function (field) {
                    field.name = field.name.replace(/\[lines\]\[\d+\]/, '[lines][' + li + ']');
                });
            });
        }

        function reindexAll() {
            blocksWrap.querySelectorAll('[data-carton-block]').forEach(function (block, ci) {
                reindexCarton(block, ci);
            });
        }

        addCartonBtn.addEventListener('click', function () {
            const ci = blocksWrap.querySelectorAll('[data-carton-block]').length;
            const block = cartonTemplate.content.cloneNode(true).querySelector('[data-carton-block]');

            block.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace('__CI__', ci);
            });

            blocksWrap.appendChild(block);
            block.querySelector('input')?.focus();
        });

        blocksWrap.addEventListener('click', function (event) {
            const addLine = event.target.closest('.js-add-line');
            const removeLine = event.target.closest('.js-remove-line');
            const removeCarton = event.target.closest('.js-remove-carton');

            if (addLine) {
                const block = addLine.closest('[data-carton-block]');
                const tbody = block.querySelector('[data-carton-lines] tbody');
                const row = lineTemplate.content.cloneNode(true).querySelector('[data-line-row]');

                tbody.appendChild(row);
                reindexAll();
                row.querySelector('input')?.focus();
                return;
            }

            if (removeLine) {
                const tbody = removeLine.closest('tbody');
                const rows = tbody.querySelectorAll('[data-line-row]');

                // Keep at least one line per carton — an empty table has no
                // affordance beyond the button, same call as every other
                // repeater in this codebase.
                if (rows.length === 1) {
                    rows[0].querySelectorAll('input').forEach(function (field) { field.value = ''; });
                    rows[0].querySelector('input[name*="[unit]"]').value = 'PCS';
                } else {
                    removeLine.closest('[data-line-row]').remove();
                }

                reindexAll();
                return;
            }

            if (removeCarton) {
                const blocks = blocksWrap.querySelectorAll('[data-carton-block]');

                if (blocks.length === 1) {
                    removeCarton.closest('[data-carton-block]').querySelectorAll('input').forEach(function (field) {
                        field.value = field.name.includes('[unit]') ? 'PCS' : '';
                    });
                } else {
                    removeCarton.closest('[data-carton-block]').remove();
                }

                reindexAll();
            }
        });
    });
    </script>
    @endpush
</x-app-layout>
