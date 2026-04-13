// Variable Declarations (using let/const instead of int/float)
let depth = 50;
let colorIter = 0;
// Note: Colors in p5.js are often handled as strings or arrays of RGB values
// You can use hexadecimal strings directly with fill()
//const palette = [
//  '#380000', '#7b3718', '#a87932', '#416b4d', '#024b35',
//  '#0d0e38', '#3c1534', '#553555', '#5e0c23', '#b05069'
//]; // jewels

const palette = [
    '#b8ccde', '#6e95ae', '#235c91', '#133968', '#081f48'
];// blue
let margin = 2.0;
let r;
let shrinker = 1;


function setup() {
  // Get the element where the sketch should live
  const container = document.getElementById('sketch-container');

  // Get the container's width and height
  let containerW = container.clientWidth;
  let containerH = container.clientHeight;

  // Create the canvas using the container's dimensions
  let canvas = createCanvas(containerW, containerH);

  // Attach the canvas element to the 'sketch-container' div
  canvas.parent('sketch-container');

  noStroke();
  background(0);

  // ... rest of your original setup code
  let coords = [0, 0, width, height];
  drawer(depth, coords, 0, 0, margin);

  canvas.elt.addEventListener('click', function() {
    background(0);
    let coords = [0, 0, width, height];
    drawer(depth, coords, 0, 0, margin);
  });
}

function drawer(depth, coords, lastdir, lastColI, margin) {
  // base case
  // console.log("depth", depth); // println() becomes console.log()
  if (depth == 0) {
    // console.log("returning");
    return;
  }
  let x0 = coords[0];
  let y0 = coords[1];
  let x1 = coords[2];
  let y1 = coords[3];
  let wid = x1 - x0;
  let hei = y1 - y0;
  r = wid / (wid + hei);

  // getCol returns the index, which is used to access the palette array
  let colI = getCol(lastColI);
  // fill() can take the hex string directly
  fill(palette[colI]);

  // draw rectangle: rect(x, y, w, h, [r])
  rect(x0, y0, wid, hei);

  // pick next direction
  let currR = random(1);
  let vertical = false;
  // Use Math.max(1.0, ...) for ensuring a minimum value
  let newMargin = Math.max(1.0, margin / shrinker);

  if (currR < r) {
    vertical = true;
  }

  // split | (Vertical Split)
  if (vertical) {
    // killit if too small
    if (x1 - x0 <= 2 * margin || y1 - y0 <= 2 * margin) { return; }
    if (x1 - x0 <= 10 * margin || y1 - y0 <= 10 * margin) {
      let prob = map(x1 - x0, 3 * margin, 10 * margin, 1, 0);
      if (random(1) < prob) { return; }
    }

    // int(random(...)) becomes floor(random(...)) or just using random and trusting array indices
    let newsplit = floor(random(x0 + 2 * margin + 1, x1 - 2 * margin));

    let newCoords1 = [x0 + margin, y0 + margin, newsplit - margin / 2 - 1, y1 - margin];
    let newCoords2 = [newsplit + margin / 2, y0 + margin, x1 - margin, y1 - margin];
    drawer(depth - 1, newCoords1, 1, colI, newMargin);
    drawer(depth - 1, newCoords2, 1, colI, newMargin);
  }
  // split - (Horizontal Split)
  else {
    // killit if too small
    if (y1 - y0 <= 2 * margin || x1 - x0 <= 2 * margin) { return; }
    if (y1 - y0 <= 10 * margin || x1 - x0 <= 10 * margin) {
      let prob = map(y1 - y0, 3 * margin, 10 * margin, 1, 0);
      if (random(1) < prob) { return; }
    }

    let newsplit = floor(random(y0 + 2 * margin + 1, y1 - 2 * margin - 1));

    let newCoords1 = [x0 + margin, y0 + margin, x1 - margin, newsplit - margin / 2 - 1];
    let newCoords2 = [x0 + margin, newsplit + margin / 2, x1 - margin, y1 - margin];

    if (lastdir == 1) {
      drawer(depth - 1, newCoords1, 2, colI, newMargin);
      drawer(depth - 1, newCoords2, 2, colI, newMargin);
    } else {
      drawer(depth - 1, newCoords1, 0, colI, newMargin);
      drawer(depth - 1, newCoords2, 0, colI, newMargin);
    }
  }
}

// Function to get the next color index
function getCol(lastColI) {
  let len = palette.length;
  // int(random(len)) becomes floor(random(len))
  let i = floor(random(len));
  while (i == lastColI) {
    i = floor(random(len));
  }
  return i;
}
