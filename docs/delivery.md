# Delivery areas

A delivery area is a WooCommerce shipping zone. Zones are checked in their order: an address
belongs to the first zone whose regions and PIN rules it matches and that has at least one
enabled delivery method. A zone can also carry a shape, a radius around a point or a polygon;
then the shopper's location has to fall inside it, and a location outside moves on to the next
zone.

## Shapes from the command line

Until the console has a map editor, shapes are set with WP-CLI:

```
wp storewright area list
wp storewright area radius <zone id> <lat> <lng> <km>
wp storewright area polygon <zone id> <file.geojson>
wp storewright area clear <zone id>
```

Polygons are read from GeoJSON, as exported by most map tools (longitude first, as the format
requires).

To see what a shopper would get:

```
wp storewright deliver IN KA 560034
wp storewright deliver IN KA 560034 --lat=12.95 --lng=77.60
```

The answer is `deliverable`, `undeliverable` with a reason (`no_area`, `no_methods`,
`outside_area`), or `approximate` when a zone has a shape and there is no location to test.

## PIN directory

`make pins` downloads the All India Pincode Directory and imports one row per PIN with its
district, state and a representative point. The point is the median of the post offices in that
PIN after dropping placeholder coordinates and offices far from the rest, so it is good for
"which area is this PIN in", not for a doorstep. Set `PINS_SOURCE_URL` to use another copy of the
same file.

Contains data from the Department of Posts, Government of India, published on data.gov.in under
the Government Open Data License, India.
